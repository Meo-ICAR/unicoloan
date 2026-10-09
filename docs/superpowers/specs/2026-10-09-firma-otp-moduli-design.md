# Firma dei moduli con OTP al destinatario — Design (rev. 3)

Data: 2026-10-09 · Rev. 2: flusso **remoto**, senza firma assistita · Rev. 3: primo provider reale **Yousign** (sandbox attivo), Intesi resta un possibile secondo adattatore · Stato: in attesa di revisione · Dipende da: sistema moduli PDF (`PdfModule`), `Document`/`DocumentType`, KYC/QAV.

## Obiettivo
Il documento viene prodotto dall'app, **inviato per la firma** al destinatario tramite il provider (Yousign); il provider manda l'OTP al destinatario, che firma; il provider dispone del documento firmato e l'app lo recupera e lo collega allo **stesso `Document`** (`is_signed = true`, `signed_at`). L'app non vede, non chiede e non conserva mai OTP né PIN.

## Decisioni
- **Un solo `Document`**: il PDF firmato si aggiunge nella collection media `signed`; l'originale resta in `documents`. `is_signed`/`signed_at` si impostano quando **tutti** i firmatari hanno firmato. Il tipo (`document_type_id`) non cambia.
- **Nessuna firma assistita.** L'operatore avvia la richiesta e ne segue lo stato; il firmatario firma da solo.
- Il provider sta dietro `App\Services\Signature\SignatureProvider`, con modello "busta di firma": l'app crea la richiesta presso il provider, poi ne legge lo stato e scarica il firmato. Driver da `config/signature.php` (`SIGNATURE_DRIVER=fake|intesi`).
- Lo stato si aggiorna **soprattutto da webhook** del provider; un comando pianificato `signature:sync` fa da **riconciliazione lenta** (Yousign sconsiglia il polling e applica rate limit): ogni ora, solo per le richieste `sent` senza eventi da oltre 30 minuti, massimo 20 richieste per esecuzione. Ogni elaborazione è **idempotente**. Il webhook deve essere raggiungibile da internet (in sviluppo serve un tunnel).
- Più firmatari (cliente, poi collaboratore, come nel QAV giuridico) sono **in sequenza** (`position`); la richiesta è completata quando l'ultimo ha firmato.
- Nessun blocco del flusso se manca la firma (solo informativo, come il KYC).
- Chi notifica il destinatario (il provider oppure l'app con un link) dipende dal provider: se la busta restituisce un `signing_url` e il provider non notifica da sé, l'app invia il link con i modelli email già esistenti (`EmailTemplate`/`MailAccount`). Questa parte la fissa il piano del provider Intesi.

## Dati (connessione `mysql`)
`signature_requests` (una busta per documento):
- `id`, `document_id` (char 36), `provider`, `provider_ref` (id busta presso il provider, nullable fino all'invio),
- `status` enum: `pending` (creata) → `sent` (inviata al provider/destinatari) → `signed`; oppure `declined`, `expired`, `cancelled`, `failed`,
- `sent_at`, `signed_at`, `expires_at` (default +7 giorni, `SIGNATURE_REQUEST_TTL_DAYS`), `last_synced_at`, `failure_reason` (testo breve, senza dati sensibili), `requested_by` (user), timestamps.
- Unica richiesta **aperta** (`pending`/`sent`) per documento.

`signature_request_signers`:
- `id`, `signature_request_id` (FK, cascade), `position` (1, 2…), `slot` (`signer1`/`signer2`, il segnaposto del modulo), `role` (`client`/`collaborator`),
- snapshot al momento dell'invio: `name`, `tax_code`, `email`, `phone`; `signer_type`/`signer_ref` (id su `clients` o `users`, senza FK),
- `status` (`waiting`, `notified`, `signed`, `declined`), `signed_at`, `provider_signer_ref`.
Nessuna FK verso `clients`/`pratiches` (connessione `mysql_proforma`).

`pdf_modules.signature_slots` (json, nullable): `[{"slot":"signer1","role":"client","page":3,"x":..,"y":..,"width":..,"height":..}]`. Le coordinate dei due QAV si ricavano dai segnaposto `{{Sig1_ev_:signer1:signature:dimension(width=50mm, height=9mm)}}` e `{{Sig2_ev_:signer2:…}}` in fase di implementazione e si caricano con il seeder. Un modulo senza `signature_slots` non è inviabile per la firma.

## Interfaccia del provider
```php
interface SignatureProvider
{
    /** Crea la busta presso il provider con il PDF, i firmatari (in ordine) e i riquadri di firma. */
    public function createEnvelope(EnvelopeData $envelope): EnvelopeRef;       // ref + signing_url per firmatario (opzionale)

    /** Stato corrente della busta e dei singoli firmatari. */
    public function envelopeStatus(string $providerRef): EnvelopeStatus;

    /** Scarica il PDF firmato (PAdES) di una busta completata. */
    public function downloadSigned(string $providerRef): string;

    /** Annulla la busta (richiesta annullata o scaduta). */
    public function cancelEnvelope(string $providerRef): void;

    /** Verifica la firma di un webhook e lo traduce in un evento normalizzato; null se non valido/ignorabile. */
    public function parseWebhook(Request $request): ?SignatureEvent;
}
```
`EnvelopeData` = PDF (contenuto), nome file, firmatari (nome, CF, email, telefono, posizione, slot, riquadro), motivo/oggetto, scadenza. Errori tipizzati: `SignatureException` con `ProviderUnavailableException`, `InvalidEnvelopeException` (dati firmatario rifiutati), `EnvelopeNotFoundException`.
`FakeSignatureProvider`: crea buste in memoria/DB di test, restituisce un `signing_url` finto, lo stato avanza solo quando un test (o il comando di sviluppo `signature:fake-complete {ref}`) lo fa avanzare, e `downloadSigned` restituisce il PDF con un marcatore verificabile.

## Flusso (servizio `SignatureRequestService`)
1. `send(Document, array $signers, User)`: verifica modulo con `signature_slots` coerenti con i firmatari, nessuna richiesta aperta, almeno email o telefono per ogni firmatario, PDF disponibile (ultima versione non firmata); crea richiesta e firmatari (`pending`); chiama `createEnvelope()` **prima** di aprire una transazione, poi in transazione `mysql` salva `provider_ref`, stato `sent`, `sent_at` e activity log `firma_inviata` (solo id). Un errore del provider lascia la richiesta `failed` con il motivo e nessun altro effetto.
2. `applyEvent(SignatureEvent)` (webhook) e `sync(SignatureRequest)` (polling) portano allo stesso metodo `reconcile()`: legge `envelopeStatus()`, aggiorna i firmatari; quando la busta è completata scarica il firmato **fuori transazione**, poi in transazione: media in `signed` (sostituisce la versione precedente della sola collection `signed`), `is_signed`, `signed_at`, stato `signed`, activity log `firma_completata`. Rielaborare lo stesso evento non duplica nulla.
3. `declined`/`expired`/`cancelled`: stato aggiornato, nessun file; il documento resta non firmato e si può creare una nuova richiesta.
4. `cancel(SignatureRequest)` chiama `cancelEnvelope()` e annulla localmente. `signature:sync` (ogni ora, configurabile) riconcilia le richieste `sent` come sopra, porta a `expired` quelle oltre `expires_at` annullando la busta, e non tocca le richieste già concluse.
5. Webhook: rotta `POST /api/signature/webhook/{provider}` che passa dal solo `parseWebhook()`; richieste non valide rispondono 400/401 senza effetti; risposta rapida (la riconciliazione può andare in coda `database`).

## UI (Filament v5)
- Azione **"Invia per firma"** sui `Document` (`DocumentsRelationManager`), visibile quando il modulo del documento ha `signature_slots`, il documento non è già firmato e non ha una richiesta aperta; e un pulsante accanto al QAV approvato nel relation manager KYC. Modale: elenco firmatari precompilato (cliente o rappresentante legale; collaboratore se il modulo ha due riquadri) con email/telefono modificabili e controllati.
- Sul documento: etichetta "In attesa di firma" / "Firmato" / "Rifiutato" / "Scaduto", azioni "Aggiorna stato" (polling immediato), "Annulla richiesta", e download del PDF firmato dalla collection `signed`.
- Autorizzazione: come le altre azioni dei documenti (`checkPiano` + permesso employee), chiave funzionalità `firma`.

## Sicurezza e privacy
- Nessun OTP/PIN nell'app. Webhook con verifica di firma (o segreto condiviso) definita dal provider; activity log solo con id; `failure_reason` senza dati personali.
- Il PDF contiene dati personali (e per il QAV dati AML) e **esce verso il provider di firma** (Yousign): serve la nomina del provider a responsabile del trattamento e la verifica della residenza dei dati (decisione dell'azienda, non tecnica).
- I PDF firmati restano sul disco `public` come gli altri `Document`: stesso pattern esistente, da rivedere insieme alla privacy dei QAV.

## Provider reali
### Yousign (primo adattatore, sandbox attivo)
Configurazione in `config/signature.php` da variabili d'ambiente (nessun valore nel codice): `YOUSIGN_API_KEY`, `YOUSIGN_BASE_URL` (default sandbox `https://api-sandbox.yousign.app/v3`, produzione `https://api.yousign.app/v3`), `YOUSIGN_WEBHOOK_SECRET`. Autenticazione con la chiave API come bearer. Client HTTP con `Illuminate\Support\Facades\Http`, **senza nuove dipendenze** (un pacchetto come `elegantly/laravel-yousign` si valuta solo con approvazione esplicita).
Mappatura dell'interfaccia sul flusso Yousign v3 (da verificare sul sandbox, i dettagli segnati con (*) non sono confermati dalla documentazione letta):
- `createEnvelope`: crea la *Signature Request* → carica il PDF in `POST /signature_requests/{id}/documents` → aggiunge i firmatari in `POST /signature_requests/{id}/signers` con `info` (nome, cognome, email, telefono, lingua `it`), `signature_level`, `signature_authentication_mode` e i campi firma per documento (`page`, `x`, `y`, `width`, `height`, dai `signature_slots`) → **attiva** la richiesta (Yousign invia la notifica via email al firmatario; con `delivery_mode` configurabile (*)). `provider_ref` = id della *Signature Request*; l'ordine dei firmatari usa l'ordinamento sequenziale di Yousign (*).
- Livello e autenticazione: `signature_authentication_mode = otp_sms` (richiede il telefono del firmatario; `otp_email`, `no_otp` non si usano). Il `signature_level` (`electronic_signature` semplice oppure `advanced_electronic_signature`, quest'ultima un'opzione del piano Yousign) è in config (`YOUSIGN_SIGNATURE_LEVEL`) e uguale per tutti i firmatari; la combinazione livello/OTP va verificata sul sandbox.
- `envelopeStatus`: lettura della *Signature Request* e dei firmatari (usata solo da `signature:sync`).
- `downloadSigned` (*): scarico dei documenti firmati della richiesta completata (endpoint da confermare nell'API reference).
- `cancelEnvelope` (*): annullamento della richiesta (endpoint da confermare).
- `parseWebhook`: rotta pubblica `POST /api/signature/webhook/yousign`; verifica `X-Yousign-Signature-256` = `sha256=` + HMAC-SHA256 del **corpo grezzo** con `YOUSIGN_WEBHOOK_SECRET`, confronto a tempo costante; eventi usati: `signer.done`, `signature_request.done`, `signature_request.expired`, e il rifiuto del firmatario (nome evento da confermare (*)). Header `X-Yousign-Retry`/`X-Yousign-Issued-At` per tracciare i tentativi; Yousign ritenta fino a 8 volte, quindi l'elaborazione è idempotente.
- Il sandbox non vale come prova legale: il livello di firma e il valore probatorio si decidono con il consulente prima del passaggio in produzione.

### Intesi (eventuale secondo adattatore, non prioritario)
La documentazione letta (PkBox SDK e CSC API v1) descrive un flusso guidato dall'app, non una busta ospitata da Intesi. Si riprende solo se Intesi risponde con un'API a busta (crea richiesta con PDF e firmatari, notifica, pagina di firma, callback, download).

## Test (PHPUnit)
Con `FakeSignatureProvider`: invio (validazioni, duplicati, errore del provider senza effetti, `createEnvelope` fuori transazione), riconciliazione idempotente (stesso evento due volte), completamento (media `signed`, originale intatto, `is_signed`/`signed_at` solo a firme complete, activity log senza segreti), firma in sequenza a due firmatari, rifiuto, scadenza (`signature:sync` annulla la busta), annullamento, webhook non valido senza effetti, download fallito che lascia la richiesta `sent` riprovabile; test di contratto riusabile per ogni provider; adattatore Yousign con `Http::fake()` (creazione richiesta/documenti/firmatari/attivazione, firma HMAC valida e non valida, retry duplicato, errori 4xx/5xx, mappatura dei livelli e dell'OTP); Filament: azione visibile/nascosta, modale e controlli, azioni di stato; seeder `signature_slots` dei QAV; comando `signature:sync`.

## Fuori ambito (fase 1)
Pagina pubblica di firma ospitata dall'app (solo se il provider non ospita la firma), firma di più documenti con un solo OTP, verifica delle firme esistenti, firma qualificata con riconoscimento, test contro il sandbox Yousign reale (si fanno a mano, non nella suite automatica); provider Intesi.

## Conferme dal sandbox Yousign (prova del 2026-10-09, un firmatario)
Confermato dalla prova end-to-end (invio, firma con OTP SMS, webhook, riconciliazione in coda):
- Flusso `createEnvelope` (richiesta, documento, firmatario, `activate`), `ordered_signers`, `signature_authentication_mode: otp_sms`, `signature_level: electronic_signature`, `locale it` e `expiration_date` in formato `Y-m-d`: accettati.
- Webhook con firma HMAC valida: accettato e riconciliato; la richiesta passa a `signed`, il documento diventa firmato e il PDF finale e' nella collection `signed` (originale intatto).
- Riquadri: coordinate in punti, origine in alto a sinistra, come poppler; il riquadro cade sopra "Firma del Cliente". **Altezza minima 37** (il valore 26 e' rifiutato): usati 40.
- Il testo del segnaposto `{{Sig…}}` non e' visibile nel PDF firmato (resta nel testo estraibile).
- In sandbox il destinatario deve avere un'email del dominio dell'organizzazione Yousign.
- `YOUSIGN_SIGNATURE_LEVEL` ammette `electronic_signature` (non `standard`).
Non ancora verificati: firma a due firmatari (QAV persona giuridica), rifiuto, annullo, scadenza.

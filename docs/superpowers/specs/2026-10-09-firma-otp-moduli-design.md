# Firma dei moduli via Intesi (OTP al destinatario) — Design (rev. 2)

Data: 2026-10-09 · Rev. 2: flusso **remoto**, senza firma assistita · Stato: in attesa di revisione · Dipende da: sistema moduli PDF (`PdfModule`), `Document`/`DocumentType`, KYC/QAV.

## Obiettivo
Il documento viene prodotto dall'app, **inviato per la firma** al destinatario tramite Intesi; Intesi manda l'OTP al destinatario, che firma; Intesi dispone del documento firmato (PAdES) e l'app lo recupera e lo collega allo **stesso `Document`** (`is_signed = true`, `signed_at`). L'app non vede, non chiede e non conserva mai OTP né PIN.

## Decisioni
- **Un solo `Document`**: il PDF firmato si aggiunge nella collection media `signed`; l'originale resta in `documents`. `is_signed`/`signed_at` si impostano quando **tutti** i firmatari hanno firmato. Il tipo (`document_type_id`) non cambia.
- **Nessuna firma assistita.** L'operatore avvia la richiesta e ne segue lo stato; il firmatario firma da solo.
- Il provider sta dietro `App\Services\Signature\SignatureProvider`, con modello "busta di firma": l'app crea la richiesta presso il provider, poi ne legge lo stato e scarica il firmato. Driver da `config/signature.php` (`SIGNATURE_DRIVER=fake|intesi`).
- Lo stato si aggiorna **con due canali insieme**: webhook del provider (se disponibile) e un comando pianificato `signature:sync` di riconciliazione (polling). Ogni elaborazione è **idempotente**.
- Più firmatari (cliente, poi collaboratore, come nel QAV giuridico) sono **in sequenza** (`position`); la richiesta è completata quando l'ultimo ha firmato.
- Nessun blocco del flusso se manca la firma (solo informativo, come il KYC).
- Chi notifica il destinatario (Intesi oppure l'app con un link) dipende dal provider: se la busta restituisce un `signing_url` e il provider non notifica da sé, l'app invia il link con i modelli email già esistenti (`EmailTemplate`/`MailAccount`). Questa parte la fissa il piano del provider Intesi.

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
4. `cancel(SignatureRequest)` chiama `cancelEnvelope()` e annulla localmente. `signature:sync` (ogni 10 minuti, configurabile) riconcilia le richieste `sent`, porta a `expired` quelle oltre `expires_at` annullando la busta, e non tocca le richieste già concluse.
5. Webhook: rotta `POST /api/signature/webhook/{provider}` che passa dal solo `parseWebhook()`; richieste non valide rispondono 400/401 senza effetti; risposta rapida (la riconciliazione può andare in coda `database`).

## UI (Filament v5)
- Azione **"Invia per firma"** sui `Document` (`DocumentsRelationManager`), visibile quando il modulo del documento ha `signature_slots`, il documento non è già firmato e non ha una richiesta aperta; e un pulsante accanto al QAV approvato nel relation manager KYC. Modale: elenco firmatari precompilato (cliente o rappresentante legale; collaboratore se il modulo ha due riquadri) con email/telefono modificabili e controllati.
- Sul documento: etichetta "In attesa di firma" / "Firmato" / "Rifiutato" / "Scaduto", azioni "Aggiorna stato" (polling immediato), "Annulla richiesta", e download del PDF firmato dalla collection `signed`.
- Autorizzazione: come le altre azioni dei documenti (`checkPiano` + permesso employee), chiave funzionalità `firma`.

## Sicurezza e privacy
- Nessun OTP/PIN nell'app. Webhook con verifica di firma (o segreto condiviso) definita dal provider; activity log solo con id; `failure_reason` senza dati personali.
- Il PDF contiene dati personali (e per il QAV dati AML) e **esce verso Intesi**: serve la nomina di Intesi a responsabile del trattamento e la verifica della residenza dei dati (decisione dell'azienda, non tecnica).
- I PDF firmati restano sul disco `public` come gli altri `Document`: stesso pattern esistente, da rivedere insieme alla privacy dei QAV.

## Dipendenze da Intesi (non bloccano la fase 1)
La documentazione letta (PkBox SDK e CSC API v1) descrive un flusso **guidato dall'app** (l'app fa partire l'OTP e lo passa alla firma). Il flusso richiesto qui è **guidato da Intesi** (invio al destinatario, OTP, firma, documento firmato presso Intesi). Va chiesto a Intesi se PKBox Remote espone un'API a "busta" (crea richiesta con PDF e firmatari, notifica al destinatario, pagina di firma ospitata, callback/stato, download del firmato). Se non c'è, il provider Intesi dovrà fornire lui la pagina di firma: un link firmato (token) a una pagina dell'app che fa partire l'OTP e conclude la firma con l'API guidata dall'app, e il resto del dominio resta identico perché passa dall'interfaccia sopra.
Da ottenere: modello di integrazione (busta ospitata o API guidata dall'app), URL e ambiente di prova con credenziali, formato dei webhook e come si verificano, notifica al destinatario (chi la invia, canale email/SMS), tipo di firma, come si crea l'identità/credenziale del firmatario, limiti di scadenza della busta.

## Test (PHPUnit)
Con `FakeSignatureProvider`: invio (validazioni, duplicati, errore del provider senza effetti, `createEnvelope` fuori transazione), riconciliazione idempotente (stesso evento due volte), completamento (media `signed`, originale intatto, `is_signed`/`signed_at` solo a firme complete, activity log senza segreti), firma in sequenza a due firmatari, rifiuto, scadenza (`signature:sync` annulla la busta), annullamento, webhook non valido senza effetti, download fallito che lascia la richiesta `sent` riprovabile; test di contratto riusabile per ogni provider (Intesi con HTTP simulato); Filament: azione visibile/nascosta, modale e controlli, azioni di stato; seeder `signature_slots` dei QAV; comando `signature:sync`.

## Fuori ambito (fase 1)
Pagina pubblica di firma ospitata dall'app (solo se Intesi non ospita la firma: rientra nel piano del provider), firma di più documenti con un solo OTP, verifica delle firme esistenti, firma qualificata con riconoscimento, provider Intesi reale.

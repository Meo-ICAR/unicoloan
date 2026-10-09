# Firma dei moduli con OTP (Intesi PKBox Remote) — Design

Data: 2026-10-09 · Stato: in attesa di revisione · Dipende da: sistema moduli PDF (`PdfModule`), `Document`/`DocumentType`, KYC/QAV.

## Obiettivo
Dopo che un modulo PDF è stato generato (QAV e altri), il firmatario lo firma con un **OTP**; il PDF firmato (PAdES) torna nell'app, collegato allo stesso `Document`, con `is_signed` e `signed_at` valorizzati. La **fase 1** costruisce tutto il flusso applicativo con un'interfaccia `SignatureProvider` e un provider finto; il provider Intesi si completa quando Intesi risponde (vedi "Dipendenze da Intesi").

## Cosa si sa di Intesi (documentazione letta il 2026-10-09)
- **PkBox SDK (Java/.NET)**: `pushOTP(alias, …)` fa generare l'OTP al server PkBox e lo invia al firmatario via SMS o email, **senza restituirlo all'app**; poi `pdfsign(file, pagina, "<new>"|"<invisible>", …, alias, pin, otp, …, x, y, w, h)` produce il PAdES sul PDF intero (all'SDK arriva solo l'hash). Con `startTransaction(alias, pin, otp, …, N)` un OTP firma N documenti. Il server è un servlet proprietario (`…/pkserver/servlet/defaulthandler`).
- **CSC API v1** (se l'istanza la espone): `credentials/sendOTP` → `credentials/authorize` (`credentialID`, `numSignatures`, `hash[]`, `PIN`, `OTP`) → SAD → `signatures/signHash`. Firma solo l'hash: il PAdES va costruito da noi.
- Non verificato: quale interfaccia espone la nostra istanza, URL, credenziali, canale OTP, tipo di firma (avanzata/qualificata), come si crea la credenziale del firmatario.

## Decisioni
- **Firma assistita in back-office** (fase 1): l'operatore avvia la firma dal documento, il firmatario riceve l'OTP sul suo telefono/email e lo detta o lo digita; l'operatore lo inserisce e conferma. Il **link a distanza** per il cliente (pagina pubblica con token) è fase 2: il modello dati lo prevede (`token`) ma non si costruisce ora.
- Il provider è dietro l'interfaccia `App\Services\Signature\SignatureProvider`; driver scelto da `config/signature.php` (`SIGNATURE_DRIVER=fake|intesi`). Il PAdES lo produce il provider (nessuna costruzione PAdES in PHP nell'app).
- Firmatari: **firmatario 1 = cliente** (per le società il rappresentante legale/esecutore); **firmatario 2 = collaboratore** (solo moduli con due segnaposto, come il QAV giuridico). Più firme sullo stesso PDF avvengono **in sequenza**: la seconda firma si applica al PDF già firmato dalla prima.
- Il PDF firmato **non sostituisce** l'originale: stesso `Document`, nuova collection media `signed`; la copia firmata è quella da considerare valida.
- OTP e PIN non vengono mai salvati né scritti nei log.
- Nessun blocco del flusso se manca la firma (solo informativo, come per il KYC).

## Dati
`signature_requests` (connessione `mysql`):
- `id`, `document_id` (char 36, il `Document` da firmare), `sequence` (1 o 2), `slot` (`signer1`/`signer2`, nome del segnaposto),
- firmatario (snapshot al momento della richiesta): `signer_type` (`client`/`user`), `signer_ref` (id su `clients` o `users`), `signer_name`, `signer_tax_code`, `signer_email`, `signer_phone`,
- `provider` (`fake`/`intesi`), `credential_ref` (alias/credentialID del firmatario sul provider, nullable finché non provisionata),
- `status` enum: `pending` → `otp_sent` → `signed`; `failed`, `cancelled`, `expired`,
- `otp_requested_at`, `otp_push_count`, `otp_attempts`, `expires_at` (scadenza della richiesta, default +24 ore), `signed_at`, `failure_reason` (testo breve, senza dati sensibili), `token` (nullable, per la fase 2),
- `requested_by` (user), timestamps. Nessuna FK verso `clients`/`pratiches` (connessione `mysql_proforma`); FK solo verso tabelle su `mysql`.

`pdf_modules.signature_slots` (json, nullable): elenco dei segnaposto di firma del modulo, `[{"slot":"signer1","role":"client","page":3,"x":..,"y":..,"width":..,"height":..}]`. Le coordinate dei due QAV si ricavano dal PDF (testo `{{Sig1_ev_:signer1:signature:dimension(width=50mm, height=9mm)}}` e `{{Sig2_ev_:signer2:…}}`) in fase di implementazione e si caricano con il seeder. Un modulo senza `signature_slots` non è firmabile.

## Interfaccia del provider
```php
interface SignatureProvider
{
    /** Crea/recupera la credenziale di firma del firmatario; ritorna il riferimento (alias/credentialID). */
    public function ensureCredential(SignerData $signer): string;

    /** Fa generare e recapitare l'OTP al firmatario (SMS/email). Non restituisce l'OTP. */
    public function pushOtp(string $credentialRef, ?string $messageTemplate = null): void;

    /** Firma il PDF (PAdES) nella posizione indicata e ritorna il PDF firmato. */
    public function signPdf(string $pdf, string $credentialRef, ?string $pin, string $otp, SignaturePlacement $placement, string $reason): string;
}
```
Errori tipizzati: `SignatureException` con sottoclassi `InvalidOtpException` (OTP errato/scaduto), `OtpLockedException`, `ProviderUnavailableException`.
`FakeSignatureProvider`: accetta l'OTP configurato (`SIGNATURE_FAKE_OTP`, default `123456`), simula gli errori sopra con OTP speciali, e "firma" restituendo il PDF con un marcatore verificabile nei test; usato in sviluppo e nei test.

## Flusso (servizio `SignatureRequestService`)
1. `request(Document, SignerData $signer, slot, User)`: verifica che il modulo del documento abbia lo slot, che non esista già una richiesta aperta per quello slot, crea `signature_requests` `pending`.
2. `sendOtp(SignatureRequest)`: `ensureCredential()` se manca, poi `pushOtp()`; stato `otp_sent`. Limite: **3 invii** per richiesta, con almeno **60 secondi** fra due invii; oltre → errore chiaro.
3. `sign(SignatureRequest, string $otp, ?string $pin)`: richiesta non scaduta e in `otp_sent`; il PDF di partenza è l'ultimo PDF disponibile del `Document` (firmato dal firmatario precedente, se c'è); chiama `signPdf()`; **dentro una transazione `mysql`**: salva il PDF firmato nella collection `signed` (sostituendo la versione precedente della stessa collection, tenendo l'originale in `documents`), imposta `is_signed`, `signed_at` quando tutte le richieste del documento sono `signed`, stato `signed`, activity log `firma_documento` con solo id (nessun OTP, PIN o contenuto). Il PDF firmato si ottiene **prima** della transazione.
4. OTP errato: `otp_attempts++`; dopo **3 tentativi** la richiesta passa a `failed` (`OtpLockedException`) e va ricreata. Errori del provider: la richiesta resta `otp_sent` (riprovabile) tranne `OtpLocked`.
5. `cancel()` e un comando pianificato `signature:expire` che porta a `expired` le richieste scadute.

## UI (Filament v5)
- Azione **"Firma con OTP"** nella tabella dei `Document` (`DocumentsRelationManager`, visibile quando `document_type` ha un modulo con `signature_slots` e il documento non è firmato da tutti) e un pulsante "Firma" accanto al QAV approvato nel relation manager KYC.
- Modale a due passi: (1) scelta firmatario (precompilato dal cliente/rappresentante legale o dal collaboratore, con telefono/email modificabili e canale OTP), pulsante "Invia OTP"; (2) campo OTP (e PIN se il provider lo richiede), pulsante "Firma". Notifiche per OTP inviato, OTP errato (tentativi residui), bloccato, firmato.
- Colonna/etichetta "Firmato" sul documento; il PDF firmato si scarica dalla collection `signed`.
- Autorizzazione: come le altre azioni dei documenti (`checkPiano` + permesso employee); chiave funzionalità `firma`. Nessun accesso ai PDF firmati senza permesso sul documento.

## Dipendenze da Intesi (non bloccano la fase 1)
Dopo la risposta di Intesi si scrive un secondo piano per `IntesiSignatureProvider`:
- se l'istanza è **CSC**: client HTTP per `sendOTP`/`authorize`/`signHash` più un componente che costruisce il PAdES con firma esterna (servizio Java con libreria aperta, chiamato da Laravel);
- se è il **servlet PkBox**: ponte Java che usa l'SDK (`pushOTP`, `pdfsign`) esposto in locale a Laravel;
- se esiste un endpoint HTTP che firma il PDF intero: client HTTP diretto.
Da ottenere da Intesi: interfaccia e URL, endpoint di firma del PDF intero, credenziali e ambiente di prova, `alias`/`credentialID` di test, come si crea la credenziale del firmatario (`ensureCredential`), canale OTP e tipo di firma, se il PIN è richiesto.

## Test (PHPUnit)
Servizio con `FakeSignatureProvider`: richiesta/duplicati, invio OTP (limite 3, intervallo 60 s, stato), firma corretta (media `signed`, `is_signed`/`signed_at` solo a firme complete, activity log senza segreti, originale intatto), OTP errato e blocco al 3° tentativo, scadenza, firme in sequenza sul secondo slot, rollback senza effetti se il provider fallisce; `ensureCredential` chiamata una sola volta; test di contratto riusabile per ogni provider (il provider Intesi dovrà superarlo con HTTP simulato); Filament: azione visibile/nascosta, flusso a due passi, notifiche; seeder `signature_slots` dei QAV; comando `signature:expire`.

## Fuori ambito (fase 1)
Link pubblico per la firma a distanza, firma di più documenti con un solo OTP (`startTransaction`), validazione/verifica delle firme esistenti, firma qualificata con identificazione video, marca temporale separata, provider Intesi reale.

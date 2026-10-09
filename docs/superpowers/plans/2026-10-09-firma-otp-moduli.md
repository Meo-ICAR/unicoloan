# Firma dei moduli con OTP (Yousign) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Inviare per la firma un `Document` (QAV e altri moduli) tramite un provider di firma con OTP al destinatario, seguirne lo stato via webhook e recuperare il PDF firmato nello stesso `Document`.

**Architecture:** Interfaccia `SignatureProvider` a "busta di firma" con `FakeSignatureProvider` (test/sviluppo) e `YousignSignatureProvider` (Http di Laravel, sandbox attivo). `SignatureRequestService` orchestra invio, riconciliazione (idempotente), annullamento e scadenza; stato aggiornato da webhook (via job in coda) più un comando `signature:sync` lento. Azione Filament "Invia per firma" sui documenti.

**Tech Stack:** Laravel 13, Filament v5, Livewire v4, PHPUnit 12, `Illuminate\Support\Facades\Http`, spatie/laravel-medialibrary (già presente), coda `database`.

**Spec:** `docs/superpowers/specs/2026-10-09-firma-otp-moduli-design.md` (rev. 3, approvata)

## Global Constraints

- **Nessuna nuova dipendenza** Composer/npm. Client Yousign con `Http`, timeout 30 s, nessun log di chiave API, secret, OTP, contenuto PDF.
- Variabili d'ambiente (già nel `.env`, i valori non vanno mai letti né scritti): `YOUSIGN_API_KEY`, `YOUSIGN_WEBHOOK_SECRET`, `YOUSIGN_BASE_URL` (default `https://api-sandbox.yousign.app/v3`), `YOUSIGN_SIGNATURE_LEVEL` (default `electronic_signature`); più `SIGNATURE_DRIVER` (default `fake`), `SIGNATURE_REQUEST_TTL_DAYS` (default `7`). Il codice legge solo `config('signature.*')`, mai `env()` fuori da `config/signature.php`.
- Un solo `Document`: il firmato va nella collection media `signed`, l'originale resta in `documents`; `is_signed`/`signed_at` solo a firme complete.
- Il PDF/hash va al provider **fuori da transazioni DB**; le scritture locali di una conclusione stanno in **una** `DB::connection('mysql')->transaction()` con il media allegato per ultimo.
- Nuovi modelli su connessione `mysql` con `protected $connection = 'mysql'`; nessuna FK verso `clients`/`pratiches` (connessione `mysql_proforma`); `document_id` è `char(36)`, `users.id` bigint.
- Webhook: rotta `POST /api/signature/webhook/{provider}` (il prefisso `/api` esiste già in `routes/api.php`), verifica HMAC sul **corpo grezzo**, confronto a tempo costante; esito 401 se non valido, 204 se valido ma ignorabile, 202 dopo aver messo in coda la riconciliazione.
- Chiave funzionalità/permesso `firma` (tabella `resources`, `ResourceSeeder`), gating `checkPiano('firma', …)`.
- Solo informativo: nessun blocco di pratiche se manca la firma. Testi UI in italiano. PHPUnit, `LazilyRefreshDatabase`, `protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];`. `vendor/bin/pint --dirty --format agent` dopo ogni modifica PHP. Solo `php artisan make:*` con `--no-interaction`. Commit con trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Non applicare migrazioni né seeder al DB di sviluppo dai task: lo fa il Task 9 con conferma dell'utente.
- I punti segnati (*) nel Task 6 sono ipotesi sull'API Yousign non confermate dalla documentazione: vanno centralizzati in costanti/metodi isolati del provider e verificati nel Task 9 sul sandbox.

## Review Focus

- Webhook con firma HMAC errata o corpo alterato → 401, nessun job, nessuna scrittura (Task 5/6).
- Stesso evento/stessa riconciliazione eseguita due volte → un solo media `signed`, nessun doppio `activity_log` (Task 4).
- `createEnvelope` che fallisce (4xx/5xx) → richiesta `failed` con motivo, nessuna richiesta `sent` fantasma, nuovo invio possibile (Task 3).
- Il provider dice "completata" ma il download fallisce → richiesta resta `sent` e riprovabile, `is_signed` resta falso (Task 4).
- Telefono italiano non in E.164 (`338 1234567`, `0039338…`) normalizzato in `+39338…`; telefono/email mancanti bloccati **prima** della chiamata al provider (Task 1/3).
- Seconda richiesta su un documento con richiesta aperta rifiutata; dopo `declined`/`expired`/`cancelled`/`failed` è consentita (Task 3).
- Firmato dal solo primo firmatario → il `Document` NON è `is_signed` e la richiesta resta `sent` (Task 4).

---

### Task 1: Configurazione, enum, DTO, interfaccia, provider finto

**Files:**
- Create: `config/signature.php`, `app/Enums/SignatureRequestStatus.php`, `app/Enums/SignerStatus.php`, `app/Enums/SignerRole.php`
- Create (namespace `App\Services\Signature`, cartella `app/Services/Signature/`): `SignatureProvider.php`, `SignatureProviderManager.php`, `FakeSignatureProvider.php`, `PhoneNumber.php`, `Dto/SignaturePlacement.php`, `Dto/EnvelopeSigner.php`, `Dto/EnvelopeData.php`, `Dto/EnvelopeRef.php`, `Dto/SignerState.php`, `Dto/EnvelopeStatus.php`, `Dto/SignatureEvent.php`, `Exceptions/SignatureException.php`, `Exceptions/ProviderUnavailableException.php`, `Exceptions/InvalidEnvelopeException.php`, `Exceptions/EnvelopeNotFoundException.php`
- Test: `tests/Unit/PhoneNumberTest.php`, `tests/Concerns/SignatureProviderContract.php`, `tests/Feature/FakeSignatureProviderTest.php`

**Interfaces — Produces:**
- Enum `SignatureRequestStatus` (string, `HasLabel`, `HasColor`): `Pending='pending'`, `Sent='sent'`, `Signed='signed'`, `Declined='declined'`, `Expired='expired'`, `Cancelled='cancelled'`, `Failed='failed'`; metodo `isOpen(): bool` (Pending|Sent) e `isFinal(): bool`.
- `SignerStatus`: `Waiting='waiting'`, `Notified='notified'`, `Signed='signed'`, `Declined='declined'`. `SignerRole`: `Client='client'`, `Collaborator='collaborator'`. Tutte con `getLabel()` italiano (Atteso/Notificato/Firmato/Rifiutato; Cliente/Collaboratore; In preparazione/Inviata/Firmata/Rifiutata/Scaduta/Annullata/Non riuscita).
- DTO `readonly`: `SignaturePlacement(int $page, float $x, float $y, float $width, float $height)`; `EnvelopeSigner(int $position, string $slot, SignerRole $role, string $firstName, string $lastName, ?string $email, ?string $phone, ?string $taxCode, SignaturePlacement $placement)`; `EnvelopeData(string $name, string $fileName, string $pdf, array $signers /* EnvelopeSigner[] ordinati per position */, ?\DateTimeInterface $expiresAt, string $externalId)`; `EnvelopeRef(string $providerRef, array $signerRefs /* slot => id firmatario presso il provider */)`; `SignerState(string $slot, SignerStatus $status, ?\Carbon\CarbonInterface $signedAt)`; `EnvelopeStatus(SignatureRequestStatus $status /* Sent|Signed|Declined|Expired|Cancelled */, array $signers /* SignerState[] */)`; `SignatureEvent(string $providerRef, string $type, string $eventId)`.
- Interfaccia:

```php
interface SignatureProvider
{
    public function name(): string;

    /** @return array<int, string> campi di contatto obbligatori per ogni firmatario: 'email' e/o 'phone' */
    public function requiredContactFields(): array;

    public function createEnvelope(EnvelopeData $envelope): EnvelopeRef;

    public function envelopeStatus(string $providerRef): EnvelopeStatus;

    /** @return string contenuto binario del PDF firmato */
    public function downloadSigned(string $providerRef): string;

    public function cancelEnvelope(string $providerRef): void;

    /** Verifica autenticità e normalizza; null = firma non valida (401); un evento con type 'ignored' = valido ma ignorabile (204). */
    public function parseWebhook(\Illuminate\Http\Request $request): ?SignatureEvent;
}
```
- `PhoneNumber::toE164(?string $raw, string $defaultPrefix = '+39'): ?string`: toglie spazi, trattini, parentesi, punti; `0039…`→`+39…`; numero che inizia con `3` (mobile italiano) o senza prefisso → `+39…`; già `+…` invariato; vuoto/non numerico → `null`.
- `SignatureProviderManager::provider(?string $name = null): SignatureProvider` (nome da `config('signature.driver')`; `fake` e `yousign`; nome sconosciuto → `InvalidArgumentException`); registrato come singleton in `AppServiceProvider` (o provider dedicato).
- `FakeSignatureProvider`: stato nella cache (`Cache::put("signature.fake.{ref}", …)`), ref `fake-<uuid>`; `requiredContactFields()` = `['email','phone']`; metodi di test/sviluppo `completeSigner(ref, slot)`, `completeAll(ref)`, `decline(ref)`, `expire(ref)`; `downloadSigned` = PDF originale + riga finale `%FAKE-SIGNED` (resta un PDF valido per `addMediaFromString`); `parseWebhook` accetta l'header `X-Fake-Signature: ok` e un JSON `{ref, type, id}`.
- `config/signature.php`: `driver`, `ttl_days`, `sync` (`interval_minutes` 60, `stale_minutes` 30, `limit` 20), `yousign` (`api_key`, `base_url`, `webhook_secret`, `signature_level`, `timeout` 30).

- [ ] **Step 1: Test che fallisce** — `PhoneNumberTest` (unit, senza DB): `'338 1234567'→'+393381234567'`, `'0039 338 1234567'→'+393381234567'`, `'+39 338-1234567'→'+393381234567'`, `'(338) 123.4567'→'+393381234567'`, `'+33612345678'→'+33612345678'`, `''→null`, `null→null`, `'abc'→null`. `tests/Concerns/SignatureProviderContract.php` = trait con test riusabili (`test_create_envelope_returns_ref_and_a_signer_ref_per_slot`, `test_new_envelope_is_sent_with_waiting_signers`, `test_completed_envelope_is_signed_and_downloadable_as_pdf`, `test_cancelled_envelope_reports_cancelled`) che usano i metodi astratti `provider(): SignatureProvider`, `completeAll(string $ref): void`, `makeEnvelope(): EnvelopeData` (due firmatari: slot `signer1` client posizione 1, `signer2` collaborator posizione 2, PDF = contenuto di `tests/Fixtures/pdf/modulo-prova.pdf`). `FakeSignatureProviderTest` usa il trait (hook `completeAll` = `$this->provider()->completeAll($ref)`) e aggiunge: stato avanza solo quando chiamato, `decline` → `Declined`, `expire` → `Expired`, `parseWebhook` senza header → `null`.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** tutti i file sopra (stile enum di `app/Enums/AmlReportStatus.php`; DTO con `final readonly class`); nessun `env()` fuori da `config/signature.php`.
- [ ] **Step 4: PASS** — `php artisan test --compact tests/Unit/PhoneNumberTest.php tests/Feature/FakeSignatureProviderTest.php`; **Step 5:** pint + commit `feat(firma): interfaccia provider, DTO, enum e provider finto`.

---

### Task 2: Migrazioni, modelli, `signature_slots`, collection `signed`

**Files:**
- Create (artisan): `app/Models/SignatureRequest.php`, `app/Models/SignatureRequestSigner.php`, migrazioni `2026_10_10_100000_create_signature_requests_table.php`, `…100100_create_signature_request_signers_table.php`, `…100200_add_signature_slots_to_pdf_modules_table.php`, factory `SignatureRequestFactory`
- Modify: `app/Models/Document.php` (`registerMediaCollections`, relazione `signatureRequests()`), `app/Models/PdfModule.php` (`signature_slots` fillable + cast `array`)
- Test: `tests/Feature/SignatureModelTest.php`

**Interfaces — Consumes:** Task 1 enum. **Produces:**
- `SignatureRequest` (`$connection='mysql'`; fillable: `document_id, provider, provider_ref, status, sent_at, signed_at, expires_at, last_synced_at, last_event_at, failure_reason, requested_by, token`; casts: `status` → `SignatureRequestStatus`, date/datetime); relazioni `document(): BelongsTo`, `signers(): HasMany` (ordinata per `position`); scope `open()` (status Pending|Sent); metodo `isOpen(): bool`.
- `SignatureRequestSigner` (`$connection='mysql'`; fillable: `signature_request_id, position, slot, role, name, first_name, last_name, tax_code, email, phone, signer_type, signer_ref, status, signed_at, provider_signer_ref`; casts `role` → `SignerRole`, `status` → `SignerStatus`).
- `Document::signatureRequests(): HasMany`; `Document::latestSignatureRequest(): ?SignatureRequest`; `registerMediaCollections()` aggiunge `addMediaCollection('signed')->useDisk('public')->singleFile()`.
- `PdfModule::$signature_slots`: `array<int, array{slot: string, role: string, page: int, x: float, y: float, width: float, height: float}>|null`.

Migrazioni (con `if (Schema::hasTable(...)) { return; }`):

```php
Schema::create('signature_requests', function (Blueprint $table) {
    $table->comment('Richieste di firma di un Document presso un provider (busta di firma).');
    $table->id();
    $table->char('document_id', 36)->index()->comment('documents.id');
    $table->string('provider', 30);
    $table->string('provider_ref')->nullable()->index();
    $table->string('status', 20)->default('pending')->index();
    $table->dateTime('sent_at')->nullable();
    $table->dateTime('signed_at')->nullable();
    $table->dateTime('expires_at')->nullable();
    $table->dateTime('last_synced_at')->nullable();
    $table->dateTime('last_event_at')->nullable();
    $table->string('failure_reason')->nullable();
    $table->unsignedBigInteger('requested_by')->nullable();
    $table->string('token', 64)->nullable()->unique()->comment('riservato alla firma a distanza via link (fase 2)');
    $table->timestamps();
});

Schema::create('signature_request_signers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('signature_request_id')->constrained('signature_requests')->cascadeOnDelete();
    $table->unsignedTinyInteger('position');
    $table->string('slot', 30);
    $table->string('role', 20);
    $table->string('name');
    $table->string('first_name');
    $table->string('last_name');
    $table->string('tax_code', 32)->nullable();
    $table->string('email')->nullable();
    $table->string('phone', 32)->nullable();
    $table->string('signer_type', 20)->nullable()->comment('client | agent | user | manual');
    $table->string('signer_ref', 64)->nullable();
    $table->string('status', 20)->default('waiting');
    $table->dateTime('signed_at')->nullable();
    $table->string('provider_signer_ref')->nullable();
    $table->timestamps();
    $table->unique(['signature_request_id', 'slot']);
});

Schema::table('pdf_modules', function (Blueprint $table) {
    $table->json('signature_slots')->nullable()->after('is_active');
});
```
(la terza migrazione ha `hasColumn` guard.)

- [ ] **Step 1: Test che fallisce** (`SignatureModelTest`): richiesta con firmatari ordinati per `position`; cast degli enum; `open()` include Pending/Sent ed esclude gli altri; `Document::latestSignatureRequest()` ritorna l'ultima per `id`; `PdfModule` salva e rilegge `signature_slots` come array; `Document` registra la collection `signed` come singleFile (aggiungere due media a `signed` lascia uno solo). Crea un `Document` come nei test KYC (`Client::create` + `$client->documents()->create(['name'=>'QAV','status'=>'caricato','spatie_collection'=>'documents'])`).
- [ ] **Step 2: FAIL**; **Step 3: Implementa** migrazioni, modelli, factory, modifiche a `Document`/`PdfModule`; **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureModelTest.php`); **Step 5:** pint + commit `feat(firma): tabelle e modelli delle richieste di firma`.

---

### Task 3: Invio della richiesta (`SignatureRequestService::send`)

**Files:** Create `app/Services/Signature/SignatureRequestService.php`, `app/Services/Signature/SignerInput.php`, `app/Services/Signature/SignerDefaults.php`, `app/Services/Signature/Exceptions/SignatureRequestException.php`; Test `tests/Feature/SignatureSendTest.php`

**Interfaces — Consumes:** Task 1–2, `DocumentType::pdfModule()` (HasOne), `PdfModule::signature_slots`, `ModuleDataResolver::findClient(Pratica)`. **Produces:**
- `SignerInput` (readonly: `string $slot, SignerRole $role, string $firstName, string $lastName, ?string $email, ?string $phone, ?string $taxCode, ?string $signerType, ?string $signerRef`) con factory statiche `fromClient(Client, string $slot): self` (per `is_person` false usa `legalRepresentative` se presente, altrimenti il cliente; nome = `first_name`, cognome = `name`), `fromAgent(\App\Models\PROFORMA\Fornitore, string $slot): self` (spezza `name` al primo spazio), `fromUser(User, string $slot): self` (spezza `name`; `phone` null).
- `SignerDefaults::for(Document $document): array<string, SignerInput>` (indicizzato per slot): documentable `Client` → slot con ruolo `client` dal cliente; documentable `Pratica` → client dal cliente trovato con `findClient`, collaboratore dall'agente (`$pratica->agente`) se c'è; se non risolvibile lo slot manca (l'operatore lo compila a mano).
- `SignatureRequestService::__construct(SignatureProviderManager $providers)`; `send(Document $document, array $signers /* SignerInput[] */, User $user): SignatureRequest`; `SignatureRequestException` (`DomainException`) con messaggi italiani.

Regole di `send()` (nell'ordine, ognuna con `SignatureRequestException` e test dedicato):
1. il `Document` ha un PDF in `documents` (`getFirstMedia('documents')` con mime `application/pdf`);
2. il modulo (`$document->documentType?->pdfModule`) ha `signature_slots` non vuoti; gli slot dei `$signers` coincidono **esattamente** con quelli del modulo;
3. nessuna richiesta aperta per il documento;
4. ogni firmatario ha nome e cognome e i campi di `provider->requiredContactFields()`; il telefono si normalizza con `PhoneNumber::toE164` e, se non valido, errore "Telefono non valido per <nome>";
5. costruzione: `position` = ordine degli slot nel modulo; `EnvelopeData(name: "<nome documento>", fileName: "<slug>.pdf", pdf: contenuto, signers, expiresAt: now()->addDays(config('signature.ttl_days')), externalId: (string) $request->id)`.
Flusso: transazione `mysql` breve che crea richiesta `pending` + firmatari `waiting`; **fuori transazione** `createEnvelope()`; su `SignatureException` → richiesta `failed` con `failure_reason` = messaggio (senza dati personali, max 200 caratteri) e rilancio come `SignatureRequestException`; su successo → transazione: `provider_ref`, `provider_signer_ref` per slot, stato `sent`, `sent_at`, firmatari `notified`, activity log `firma_inviata` (`performedOn($document)`, `causedBy($user)`, proprietà solo `signature_request_id` e `provider`).

- [ ] **Step 1: Test che fallisce** (`SignatureSendTest`, driver `fake` via `config(['signature.driver'=>'fake'])`; helper `documentWithModule()` crea `DocumentType`, `PdfModule` con `signature_slots` a due slot, `Document` con media PDF (`addMediaFromString(file_get_contents('tests/Fixtures/pdf/modulo-prova.pdf'))`), `signers()` valide): invio riuscito (stato `sent`, `provider_ref` valorizzato, due firmatari `notified` con `provider_signer_ref`, `expires_at` ≈ +7 giorni, activity `firma_inviata` senza email/telefono nelle proprietà); documento senza PDF; modulo senza slot; slot non coincidenti; richiesta già aperta rifiutata ma consentita dopo `declined`; telefono mancante → errore **senza** chiamare il provider (provider mockato: `createEnvelope` mai invocato); telefono `338 1234567` normalizzato `+393381234567` nei dati passati al provider (mock con `shouldReceive('createEnvelope')->with(Mockery::on(...))`); `createEnvelope` che lancia `ProviderUnavailableException` → richiesta `failed`, nessun `provider_ref`, nuovo invio possibile; `createEnvelope` invocato fuori da transazione (confronto `transactionLevel()` con la baseline come nel test KYC). `SignerDefaults`: documento di un cliente persona fisica → slot client precompilato; società con rappresentante legale → dati del rappresentante.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureSendTest.php`); **Step 5:** pint + commit `feat(firma): invio della richiesta di firma`.

---

### Task 4: Riconciliazione, completamento, annullo e scadenza

**Files:** Modify `app/Services/Signature/SignatureRequestService.php`; Test `tests/Feature/SignatureReconcileTest.php`

**Interfaces — Consumes:** Task 1–3. **Produces:**
- `reconcile(SignatureRequest $request): SignatureRequest` — idempotente. Se la richiesta non è `Sent` ritorna invariata. Legge `provider->envelopeStatus($ref)`, aggiorna `last_synced_at` e lo stato dei singoli firmatari (per `slot`). Esiti:
  - busta `Signed` → `downloadSigned()` **fuori transazione**; poi `DB::connection('mysql')->transaction`: ri-lettura con `lockForUpdate()`; se già `Signed` esce; stato `signed`, `signed_at`, `$document->forceFill(['is_signed'=>true,'signed_at'=>…])->save()`, activity log `firma_completata` (solo id), e **per ultimo** `addMediaFromString($pdf)->usingFileName(<slug>-firmato.pdf)->toMediaCollection('signed')`;
  - busta `Declined|Expired|Cancelled` → stato corrispondente, firmatari aggiornati, nessun file, `failure_reason` = null;
  - busta ancora `Sent` (anche con alcuni firmatari firmati) → solo aggiornamento dei firmatari; **`Document` non firmato**;
  - errore del download → richiesta resta `Sent`, eccezione `ProviderUnavailableException` rilanciata come `SignatureRequestException`, nessuna scrittura parziale.
- `cancel(SignatureRequest $request, User $user): SignatureRequest` — solo se aperta; `cancelEnvelope()` poi stato `cancelled` e activity `firma_annullata`.
- `expireOverdue(int $limit = 20): int` — per le richieste `Sent` con `expires_at` passata: tenta `cancelEnvelope()` (errori ignorati e loggati senza dati sensibili), stato `expired`; ritorna quante.
- `applyWebhook(SignatureEvent $event): void` — trova la richiesta per `provider_ref`, aggiorna `last_event_at`; se non trovata lo ignora; non cambia stato da solo: dispatch di `ReconcileSignatureRequest` (Task 5). Qui il metodo ritorna `?SignatureRequest` per il job.

- [ ] **Step 1: Test che fallisce** (`SignatureReconcileTest`, `FakeSignatureProvider` + helper del Task 3): completamento a due firmatari (dopo `completeSigner(signer1)` il documento NON è firmato e la richiesta resta `sent`, firmatario 1 `signed`; dopo `completeAll` il documento è `is_signed`, `signed_at` valorizzato, media `signed` presente e originale in `documents` intatto, activity `firma_completata`); **idempotenza** (due `reconcile` consecutivi → un solo media `signed`, un solo activity `firma_completata`); download che fallisce (provider mockato che lancia sul download dopo `Signed`) → richiesta `sent`, `is_signed` falso, nessun media; rifiuto (`decline`) → `declined`; scadenza lato provider (`expire`) → `expired`; `cancel` aperta → `cancelled` e busta annullata, `cancel` su richiesta chiusa → eccezione; `expireOverdue` con `expires_at` nel passato → `expired`, richiesta non ancora scaduta intatta; `applyWebhook` con ref sconosciuto → `null` senza errori; una nuova richiesta dopo `declined` è possibile (Task 3).
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureReconcileTest.php tests/Feature/SignatureSendTest.php`); **Step 5:** pint + commit `feat(firma): riconciliazione, completamento, annullo e scadenza`.

---

### Task 5: Webhook, job di riconciliazione e comandi

**Files:**
- Create: `app/Http/Controllers/Api/SignatureWebhookController.php`, `app/Jobs/ReconcileSignatureRequest.php`, `app/Console/Commands/SyncSignatureRequests.php`, `app/Console/Commands/FakeCompleteSignature.php`
- Modify: `routes/api.php` (rotta), `routes/console.php` (schedule)
- Test: `tests/Feature/SignatureWebhookTest.php`, `tests/Feature/SignatureSyncCommandTest.php`

**Interfaces — Consumes:** Task 1–4. **Produces:**
- Rotta `Route::post('/signature/webhook/{provider}', SignatureWebhookController::class)->middleware('throttle:120,1')->name('api.signature.webhook');`
- `SignatureWebhookController::__invoke(Request $request, string $provider): Response`: `$providers->provider($provider)` (nome non configurato/sconosciuto → 404); `parseWebhook()`: `null` → **401**; evento `type === 'ignored'` → **204**; altrimenti `$service->applyWebhook($event)` e, se trova una richiesta, `ReconcileSignatureRequest::dispatch($request->id)` → **202**.
- `ReconcileSignatureRequest` (`ShouldQueue`, `ShouldBeUnique` per id richiesta, `tries = 3`, `backoff = [60, 300, 900]`): `handle(SignatureRequestService)` → `reconcile()`.
- `php artisan signature:sync {--limit=} {--stale-minutes=}`: prende `Sent` con `last_event_at` e `last_synced_at` entrambi più vecchi di `stale_minutes` (default config, 30), al massimo `limit` (default 20), esegue `reconcile()` di ognuna (errori per richiesta loggati senza dati sensibili, non interrompono il ciclo) e poi `expireOverdue($limit)`; stampa un riepilogo. Schedulato `->hourly()->withoutOverlapping()` in `routes/console.php`.
- `php artisan signature:fake-complete {ref} {--decline} {--expire}`: solo con driver `fake` e **non in produzione** (altrimenti fallisce con messaggio); usa i metodi del `FakeSignatureProvider` e poi dispatch del job (sync).

- [ ] **Step 1: Test che fallisce** — `SignatureWebhookTest` (`Queue::fake()`): header assente/errato → 401 e **nessun job**; evento valido per ref noto → 202 e job in coda con l'id giusto, `last_event_at` aggiornato; ref sconosciuto → 202/204 senza job (scegliere 204) ; provider `sconosciuto` → 404; stesso evento due volte → due dispatch ma il job è unico (`ShouldBeUnique`) e la riconciliazione eseguita due volte non duplica il media (usa il test del Task 4). `SignatureSyncCommandTest`: richieste `Sent` recenti (con evento da <30 minuti) **non** vengono toccate; richieste vecchie vengono riconciliate (`FakeSignatureProvider::completeAll` prima → dopo il comando il documento è firmato); `--limit=1` ne elabora una; richieste oltre `expires_at` scadono; un errore su una richiesta non ferma le altre; `signature:fake-complete` rifiutato con `app()->detectEnvironment(fn () => 'production')` simulato.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureWebhookTest.php tests/Feature/SignatureSyncCommandTest.php`); **Step 5:** pint + commit `feat(firma): webhook, job di riconciliazione e comando signature:sync`.

---

### Task 6: Adattatore Yousign

**Files:** Create `app/Services/Signature/YousignSignatureProvider.php`, `app/Console/Commands/CheckSignatureProvider.php`; Modify `app/Services/Signature/SignatureProviderManager.php`; Test `tests/Feature/YousignSignatureProviderTest.php`, `tests/Feature/CheckSignatureProviderCommandTest.php`

**Interfaces — Consumes:** Task 1 (interfaccia, DTO, eccezioni, trait `SignatureProviderContract`). **Produces:** `YousignSignatureProvider(array $config)` (`api_key`, `base_url`, `webhook_secret`, `signature_level`, `timeout`); `name()` = `yousign`; `requiredContactFields()` = `['email', 'phone']`; comando `php artisan signature:check` (legge `config('signature.driver')`, per `yousign` fa `GET {base_url}/signature_requests?limit=1` in **sola lettura**, stampa "Provider: yousign · URL: <base_url> · esito: OK|KO (<codice HTTP>)", **mai la chiave né il secret**; per `fake` stampa che il driver è finto).

Mappatura (tutte le chiamate con `Http::withToken($apiKey)->acceptJson()->timeout($timeout)->baseUrl($baseUrl)`; costanti dei percorsi in un unico punto della classe; dove c'è (*) l'ipotesi è da verificare sul sandbox nel Task 9):
- `createEnvelope(EnvelopeData $e)`:
  1. `POST /signature_requests` JSON `{name, delivery_mode: "email", ordered_signers: true, timezone: "Europe/Rome", external_id: <externalId>, expiration_date: <Y-m-d da expiresAt> (*)}` → `id`.
  2. `POST /signature_requests/{id}/documents` multipart: `file` (contenuto, nome file), `nature = "signable_document"` → `document id`.
  3. Per ogni firmatario **in ordine di `position`**: `POST /signature_requests/{id}/signers` JSON `{info: {first_name, last_name, email, phone_number (E.164), locale: "it"}, signature_level: <config>, signature_authentication_mode: "otp_sms", fields: [{type: "signature", document_id, page, x, y, width, height}]}` → `signer id`; ordine di firma = ordine di creazione (*).
  4. `POST /signature_requests/{id}/activate`.
  Ritorna `EnvelopeRef(providerRef: <id richiesta>, signerRefs: [slot => signer id])`. Qualsiasi fallimento dopo la creazione tenta `DELETE /signature_requests/{id}` (best effort, errori ignorati) e lancia l'eccezione tipizzata.
- `envelopeStatus($ref)`: `GET /signature_requests/{id}` (campo `status`) e `GET /signature_requests/{id}/signers` (per ogni firmatario `status` e data di firma (*)). Mappatura busta: `done` → `Signed`; `expired` → `Expired`; `canceled`|`cancelled` → `Cancelled`; `rejected`|`declined` → `Declined`; `ongoing`|`approval`|`draft` → `Sent` (valori esatti da verificare (*)). Firmatario: `signed` → `Signed`; `declined`|`aborted` → `Declined`; `notified`|`verified`|`initiated` → `Notified`; altro → `Waiting`. L'identificazione del firmatario sul `slot` avviene confrontando `provider_signer_ref` con l'id (il provider non conosce gli slot: `envelopeStatus` restituisce `SignerState` con `slot` = id del firmatario e il service lo mappa tramite `provider_signer_ref`; **definire nel Task 4 questa mappatura** se non già così).
- `downloadSigned($ref)`: `GET /signature_requests/{id}/documents/download` → se `Content-Type` è `application/pdf` ritorna il corpo; se è `application/zip` estrae il primo `.pdf` (ZipArchive); altrimenti `InvalidEnvelopeException`.
- `cancelEnvelope($ref)`: `POST /signature_requests/{id}/cancel` JSON `{reason: "other", custom_note: "Annullata dall'operatore"}` (*).
- `parseWebhook(Request $r)`: header `X-Yousign-Signature-256` deve essere `'sha256='.hash_hmac('sha256', $r->getContent(), $webhookSecret)` (confronto `hash_equals`); header assente/errato o secret vuoto → `null`. JSON `{event_id, event_name, data: {signature_request: {id}}}` (*): `signer.done`, `signature_request.done`, `signature_request.expired`, `signature_request.canceled`, `signer.declined`/`signature_request.declined` (*) → `SignatureEvent(providerRef: data.signature_request.id, type: <event_name>, eventId: <event_id>)`; qualsiasi altro nome evento → `SignatureEvent` con `type 'ignored'`.
- Errori HTTP: 401/403 → `ProviderUnavailableException("Chiave API Yousign non valida")`; 404 → `EnvelopeNotFoundException`; 400/422 → `InvalidEnvelopeException` con il campo `detail` della risposta se presente (troncato a 200 caratteri); 429 e 5xx e `ConnectionException` → `ProviderUnavailableException` (con un solo retry per 5xx/connessione: `->retry(2, 500, throw: false)`).

- [ ] **Step 1: Test che fallisce** (`YousignSignatureProviderTest`, `Http::fake([...])`): sequenza di `createEnvelope` con due firmatari (verifica con `Http::assertSentInOrder` e asserzioni sul corpo: `ordered_signers` vero, `otp_sms`, `locale it`, telefono `+39…`, campi firma con `page/x/y/width/height` dei riquadri, creazione dei firmatari in ordine di posizione, `activate` per ultimo, header `Authorization: Bearer <chiave di test>`), ritorno di `EnvelopeRef` con `signerRefs` per slot; errore 422 al secondo firmatario → tentativo di `DELETE` della richiesta e `InvalidEnvelopeException`; 401 → messaggio chiave non valida; 500 con retry; `envelopeStatus` per `ongoing`/`done`/`expired`/`canceled` e firmatari; `downloadSigned` con PDF e con ZIP (crea uno zip in memoria con `ZipArchive`); `cancelEnvelope`; `parseWebhook` con HMAC valido (calcolato sul corpo grezzo), con corpo alterato di un byte → `null`, senza header → `null`, con evento sconosciuto → `type 'ignored'`; trait `SignatureProviderContract` eseguito contro `Http::fake` configurato per simulare busta nuova → completata (hook `completeAll` riconfigura il fake con `done`/`signed`). `CheckSignatureProviderCommandTest`: con `Http::fake` 200 stampa OK e **non contiene** la chiave di test nell'output; con 401 stampa KO e codice di uscita 1; con driver `fake` esce 0.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; registra `yousign` nel manager (config `signature.yousign`); **Step 4: PASS** (`php artisan test --compact tests/Feature/YousignSignatureProviderTest.php tests/Feature/CheckSignatureProviderCommandTest.php`); **Step 5:** pint + commit `feat(firma): adattatore Yousign`.

---

### Task 7: Interfaccia Filament

**Files:**
- Create: `app/Filament/Actions/SendForSignatureAction.php`
- Modify: `app/Filament/Resources/RelationManagers/DocumentsRelationManager.php`, `app/Filament/Resources/Clients/RelationManagers/KycQuestionnairesRelationManager.php`, `database/seeders/ResourceSeeder.php` (chiave `firma`)
- Test: `tests/Feature/SignatureActionsTest.php`

**Interfaces — Consumes:** Task 1–5. **Produces:**
- `SendForSignatureAction::make(?string $name = 'sendForSignature')`: azione Filament (namespace `Filament\Actions\`) valida su un `Document` (nel RM KYC la risolve da `$record->document`). **Visibile** quando: `checkPiano('firma', …)`; il documento ha un modulo con `signature_slots` (via `documentType->pdfModule`); non è `is_signed`; non ha una richiesta aperta. Modale: una `Section` per ogni slot del modulo ("Firmatario: Cliente"/"Collaboratore") con `first_name`, `last_name`, `email`, `phone`, `tax_code` precompilati da `SignerDefaults::for($document)` (modificabili); `->action()` costruisce i `SignerInput` e chiama `SignatureRequestService::send()`; `SignatureRequestException` → notifica danger con il messaggio; successo → notifica "Richiesta di firma inviata" (se il provider ha `requiredContactFields`, i campi corrispondenti sono `required()`).
- In `DocumentsRelationManager`: colonna `signature_state` (badge: "Non firmato" grigio / "In attesa di firma" info / "Firmato" success / "Rifiutata"/"Scaduta" warning — da `latestSignatureRequest()` e `is_signed`), azioni `SendForSignatureAction`, `refreshSignature` ("Aggiorna stato", visibile con richiesta aperta, chiama `reconcile()` con gestione errori), `cancelSignature` ("Annulla richiesta di firma", `requiresConfirmation`, visibile con richiesta aperta), `downloadSigned` ("Scarica firmato", visibile con `is_signed`, scarica il media di `signed`). Eager loading: `->with('signatureRequests')` per evitare N+1.
- `ResourceSeeder`: nuova voce `['key' => 'firma', 'name' => 'Firma documenti', 'group' => 'Anagrafiche']`.
- Nel RM KYC: l'azione `SendForSignatureAction` accanto a `approve`/`print`, visibile per questionari `Approved` con documento.

- [ ] **Step 1: Test che fallisce** (Livewire sul `DocumentsRelationManager` del cliente, `Filament::setCurrentPanel('admin')`, `actingAs(User::factory()->create())`, driver `fake`; verificare le API v5 in `vendor/filament/**` e nei test esistenti `KycRelationManagerTest`/`PdfModuleResourceTest`): azione visibile per documento con modulo firmabile e nascosta senza slot, se firmato o con richiesta aperta; invio dalla modale crea `SignatureRequest` `sent` con i dati compilati; telefono mancante → notifica danger e nessuna richiesta; "Aggiorna stato" porta il documento a firmato dopo `completeAll`; "Annulla" porta la richiesta a `cancelled`; "Scarica firmato" visibile solo se firmato; colonna `signature_state` con i quattro stati; il RM KYC mostra l'azione per un questionario approvato con documento firmabile.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** (aggiungere `'firma'` al seeder senza eseguirlo sul DB di sviluppo); **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureActionsTest.php tests/Feature/KycRelationManagerTest.php tests/Feature/FilamentResourceDiscoveryTest.php`); **Step 5:** pint + commit `feat(firma): azioni Filament di invio, stato e download`.

---

### Task 8: Riquadri di firma dei moduli (`signature_slots`)

**Files:** Modify `app/Filament/Resources/PdfModules/Schemas/PdfModuleForm.php`; Create `database/seeders/SignatureSlotsSeeder.php`; Test `tests/Feature/SignatureSlotsSeederTest.php`, estendere `tests/Feature/PdfModuleResourceTest.php` (solo aggiunte)

**Interfaces — Produces:** editor `signature_slots` nel form del modulo (`Repeater` con `slot` (Select `signer1`/`signer2`), `role` (Select `client`/`collaborator`), `page`, `x`, `y`, `width`, `height` numerici, `required`); `SignatureSlotsSeeder` idempotente (imposta `signature_slots` solo se è `null` o vuoto) con le coordinate misurate sul testo dei segnaposto `{{Sig1_ev_…}}`/`{{Sig2_ev_…}}` (poppler, origine in alto a sinistra, punti, A4 595 × 842; riquadro 50 mm × 9 mm = 142 × 26 pt; **ipotesi** che Yousign usi la stessa origine/unità, da verificare nel Task 9):
  - `QAV Persona fisica`: `signer1`, ruolo `client`, pagina **3**, x 345, y 748, width 142, height 26.
  - `QAV Persona giuridica`: `signer1`, ruolo `client`, pagina **6**, x 330, y 345, width 142, height 26; `signer2`, ruolo `collaborator`, pagina **6**, x 40, y 655, width 142, height 26.
  Il seeder cerca i moduli per `name` (`QAV Persona fisica`, `QAV Persona giuridica`) e salta quelli assenti.

- [ ] **Step 1: Test che fallisce:** seeder: imposta gli slot sui due QAV con le coordinate sopra; idempotente; **non** sovrascrive slot già presenti; ignora moduli mancanti senza errore; form: `Livewire::test(EditPdfModule::class, ['record' => …])->fillForm(['signature_slots' => [[…]]])->call('save')` salva e rilegge lo stesso array; slot con `page` mancante → errore di validazione.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; **Step 4: PASS** (`php artisan test --compact tests/Feature/SignatureSlotsSeederTest.php tests/Feature/PdfModuleResourceTest.php`); **Step 5:** pint + commit `feat(firma): riquadri di firma dei moduli e seeder dei QAV`.

---

### Task 9: Verifica finale e messa in servizio (con conferma dell'utente)

- [ ] **Step 1:** `php artisan test --compact tests/Feature/Signature*.php tests/Feature/YousignSignatureProviderTest.php tests/Feature/FakeSignatureProviderTest.php tests/Feature/CheckSignatureProviderCommandTest.php tests/Unit/PhoneNumberTest.php tests/Feature/Kyc*.php tests/Feature/PdfModuleResourceTest.php tests/Feature/FilamentResourceDiscoveryTest.php` → tutto verde; poi chiedere all'utente se eseguire l'intera suite (sono noti 6 test falliti già su `main`: blacklist dipendenti, `checkPiano`, promemoria documenti).
- [ ] **Step 2: Aggiungere a `.env.example` i soli nomi** delle variabili (senza valori): `SIGNATURE_DRIVER=fake`, `SIGNATURE_REQUEST_TTL_DAYS=7`, `YOUSIGN_API_KEY=`, `YOUSIGN_BASE_URL=https://api-sandbox.yousign.app/v3`, `YOUSIGN_WEBHOOK_SECRET=`, `YOUSIGN_SIGNATURE_LEVEL=electronic_signature`; commit.
- [ ] **Step 3: Messa in servizio sul DB di sviluppo (chiedere conferma prima):** `php artisan migrate`; `php artisan db:seed --class=ResourceSeeder` (chiave `firma`); `php artisan db:seed --class=SignatureSlotsSeeder`; impostare `SIGNATURE_DRIVER=yousign` nel `.env` (lo fa l'utente); riavviare il worker della coda (`php artisan queue:work`) perché i job di riconciliazione girano in coda `database`.
- [ ] **Step 4: Verifica in sola lettura del sandbox (chiedere conferma):** `php artisan signature:check` deve stampare OK senza mostrare la chiave.
- [ ] **Step 5: Prova end-to-end sul sandbox (chiedere conferma, usa contatti reali dell'utente):** inviare un QAV per la firma a email/telefono dell'utente, firmare con l'OTP SMS, verificare che il webhook (URL pubblico o tunnel `/api/signature/webhook/yousign`, secret in `YOUSIGN_WEBHOOK_SECRET`) metta in coda la riconciliazione e che il documento diventi firmato con PDF in `signed`. Annotare e riportare nella spec le conferme/correzioni dei punti (*): nomi e valori degli stati (busta e firmatario), ordine di firma sequenziale, evento di rifiuto, endpoint di annullo e suo corpo, risposta di `documents/download` (PDF o ZIP), campo `expiration_date`, livello di firma compatibile con `otp_sms`, **origine e unità delle coordinate dei riquadri** (se non coincidono con quelle poppler, correggere il `SignatureSlotsSeeder`), e se il testo dei segnaposto `{{Sig1_ev_…}}` risulta **visibile** nel PDF firmato (in tal caso chiedere una revisione dei QAV o rimuoverlo nella generazione).
- [ ] **Step 6:** aggiornare la spec con le conferme del sandbox, commit.

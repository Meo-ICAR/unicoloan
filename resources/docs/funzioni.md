# Funzioni dell'applicazione — quadro per chi sviluppa

> Complementare a `domain-model.md` (modelli e connessioni). Qui c'è **cosa fa l'app, funzione per funzione**, dove sta nel codice, quali tabelle tocca e quali regole non ovvie ci sono. Aggiornato al 10/10/2026. Percorsi relativi alla radice del repository.

## 0. Come leggere questo documento

- Stack: Laravel 13, PHP 8.4, Filament 5, Livewire 4, PHPUnit 12. Pannelli Filament: `admin` (`app/Providers/Filament/AdminPanelProvider.php`) e `agenti` (`AgentiPanelProvider.php`, percorso `/agenti`, per i produttori).
- Convenzione di cartella: ogni risorsa Filament vive in `app/Filament/Resources/<Nome>/` con `Pages`, `Schemas`, `Tables`, `RelationManagers`. Le azioni riusabili sono in `app/Filament/Actions/`.
- Test: `php artisan test --compact <file>`. I test che toccano modelli su `mysql_proforma` dichiarano `$connectionsToTransact = ['mysql', 'mysql_proforma']`.
- **Il database è di sviluppo**: modifiche di schema e dati sono libere (le migration sono idempotenti con `hasTable/hasColumn`).

## 1. Database e connessioni

| Connessione | Database | Contenuto principale |
|---|---|---|
| `mysql` (default) | `unicoloan` | documenti, plichi (`tasks`), firma, KYC, moduli PDF, scadenziario, `api_calls`, utenti |
| `mysql_proforma` | `proforma` | `clients` (`Client`), `pratiches` (`Pratica`), `client_mandates`, `client_relations`, `pratica_clients`, `tipoprodotto`, `clientis`, `fornitores`, provvigioni |
| `mysql_unicooam` | `unicooam` | `employees` |
| `mysql_unicobpm` | `unicobpm` | `employee_types`, `resources` (permessi/abilitazioni) |

Regole:
- Ogni modello fuori dalla connessione di default **deve** dichiarare `protected $connection`, altrimenti eredita quella del modello da cui lo si raggiunge (vedi `domain-model.md` §2).
- Nessuna FK tra database. Le tabelle di proforma si creano con `Schema::connection('mysql_proforma')` nelle migration.
- Decisione 10/10/2026: **Employee, Fornitore e Clienti (istituti/mandanti)** si gestiscono in unicooam e qui sono tabelle di lookup (solo lettura). **Client** (clienti finali) resta di unicoloan.
- Morph map (`app/Providers/AppServiceProvider.php`): `branch`, `client`, `cliente` (=istituto `Clienti`), `company`, `document`, `employee`, `fornitore`, `pratica`, `website`. I documenti di un cliente hanno `documentable_type = 'client'`, di una pratica `'pratica'`. Le tabelle di proforma (fatture, indirizzi) usano ancora il nome completo della classe: non toccarle.

## 2. Clienti

Risorsa: `app/Filament/Resources/Clients/` (form in `Schemas/ClientForm.php`, relation manager in `RelationManagers/`).

### 2.1 Controlli sul codice fiscale e sulla P.IVA
- `app/Services/CodiceFiscaleDecoder.php`: `hasValidChecksum()` verifica il carattere di controllo; `decodeVerified()` ricava data di nascita, sesso e luogo (comuni in `database/data/comuni-catastali.json`, gestita l'omocodia). Nel form, uscendo dal campo codice fiscale di una persona fisica, se il checksum è giusto compila nascita, sesso e luogo; altrimenti avviso.
- `app/Services/PartitaIva.php`: formato + algoritmo di Luhn. Per le società avvisa se la P.IVA non è valida.

### 2.2 Interrogazioni a servizi esterni (accanto al campo codice fiscale / P.IVA)
Tutte e tre sono azioni a pagamento con conferma, **partono solo da un cliente salvato e senza modifiche pendenti** sui campi inviati (`ClientForm::ensureSaved()`), e registrano ogni chiamata in `api_calls` (§10).

| Azione | Servizio | Cosa fa |
|---|---|---|
| Cerved | `app/Services/CervedService.php` | score impresa per codice fiscale; mostra denominazione, score, categoria |
| Verifica PEP | `app/Services/AmlScreeningService.php` (sanctions.io, `GET /search/`, `data_source=PEP`) | elenca le corrispondenze con percentuale; **non** spunta `is_pep` da solo |
| Openapi (solo società) | `app/Services/OpenApiCompanyService.php` | dati societari (`IT-advanced`, soci con quota ≥ 10% anche da `IT-shareholders`) |

Dopo Openapi (`app/Services/CompanyShareholderImporter.php`):
- compila ragione sociale, PEC, Ateco (`62.01`), iscrizione CCIAA, forma giuridica, SDI, stato attività, inizio attività e ultimo bilancio (anno, capitale, fatturato, patrimonio netto, dipendenti);
- salva/aggiorna la sede principale ("Sede legale") nelle `branches` del cliente;
- crea un `Client` per ogni socio (persona: con dati di nascita dal codice fiscale; società: persona giuridica) e il legame in `client_relations` con la quota (`is_titolare` se > 50%);
- se `legal_representative_id` è vuoto lo valorizza con il socio persona fisica titolare (o con la quota più alta). Non sovrascrive mai un valore esistente.

Variabili `.env`: `CERVED_API_KEY`, `CERVED_URL`, `CERVED_COST_PER_CALL`, `AML_API_KEY`, `AML_API_URL` (default `https://api.sanctions.io`), `AML_MIN_SCORE`, `AML_COST_PER_CALL`, `OPENAPI_TOKEN`, `OPENAPI_COMPANY_URL`, `OPENAPI_COST_ADVANCED`, `OPENAPI_COST_SHAREHOLDERS`. Configurate in `config/services.php`.

### 2.3 Altre schede del cliente
- **Cariche sociali** (`ClientRelationsRelationManager`): soci e cariche, solo per le società; il nome è un link alla scheda del socio.
- **Documenti**, **Mandati Cliente**, **Impegni di terzi**, **KYC (adeguata verifica)**, **Siti web**, **Indirizzi** (`BranchesRelationManager`, nascosto per le persone fisiche).
- La scheda Compliance AML contiene PEP, sanzioni, esenzione art. 108, stato verifica (`status`: raccolta_dati, valutazione_aml, approvata, sos_inviata, chiusa) e consensi privacy.

## 3. Mandato e pratica

### 3.1 Pulsante "Mandato e pratica" (scheda cliente)
`app/Filament/Actions/CreaMandatoPraticaAction.php` → `app/Services/MandatoPraticaCreator.php`. Chiede tipo prodotto, istituto, importo, scopo (il tipo prodotto è filtrato per persona fisica/giuridica e **non** include i prodotti di terzi). Crea:
- `ClientMandate` (numero `MAND-000001-2026` da `ClientMandate::nextNumber()`, stato `attivo`, scadenza +2 anni) — **solo se** il prodotto ha `requires_mandate`;
- `Pratica` (`PR-AAMMGG-XXXXXX`, stato `INSERITA`, legata al cliente da `codice_fiscale` = codice fiscale o P.IVA);
- per i prodotti diversi da cessione/delega, il cliente come **richiedente** in `pratica_clients`.
Disabilitato se il cliente non ha codice fiscale / P.IVA.

### 3.2 Richiedente, coobbligati, garanti
`PraticaClientsRelationManager` (scheda della pratica) su `pratica_clients` (`app/Models/PraticaClient.php`, ruoli in `app/Enums/PraticaClientRole.php`). Visibile solo se `Pratica::isCessioneDelega()` è falso (prodotto che non contiene "cession" né "deleg"). Nessun limite al numero di richiedenti; unico vincolo: lo stesso cliente non ha due volte lo stesso ruolo.

### 3.3 Impegni di terzi
`ImpegniTerziRelationManager` (scheda cliente): pratiche con `is_notowned = true`, non di lavorazione. "Aggiungi impegno" crea una `Pratica` `TERZI-XXXXXXXX` in stato `PERFEZIONATA`; tutti i campi sono facoltativi; il tipo prodotto elenca tutti i prodotti adatti al tipo di cliente.

### 3.4 Tipi prodotto (`tipoprodotto`, risorsa `Tipoprodottos`)
Flag: `for_person`, `for_company`, `requires_mandate`, `is_third_party` (finanziamento/impegno di terzi, esempi: Cassa mutua, Pignoramento, Assegno di mantenimento, Sindacato). Cessione, Delega, TFS e "Altra delegazione importo contenuto" sono solo persona fisica; Utenza e i prodotti di terzi non richiedono il mandato.

## 4. Documenti

### 4.1 Scheda Documenti (`app/Filament/Resources/RelationManagers/DocumentsRelationManager.php`)
Usata da clienti, pratiche, istituti, produttori, aziende, organigramma. Funzioni:
- **Tipo documento filtrato per modello** (`OWNER_TYPE_FLAGS`: `is_client`, `is_practice`, `is_principal`, `is_agent`, `is_employee`, `is_company`); su "Nuovo" sono esclusi i tipi già presenti sul record.
- **Scarica**: per i tipi con modulo PDF attivo (cliente con `is_client`, pratica con `is_practice` e cliente collegato) compila e scarica il PDF (`PdfFormFiller`); per i template scaricabili scarica il file.
- **Colonna OTP / Firma**: per i tipi con `is_signed` apre il modale di invio per firma (`SendForSignatureAction`, §6). Se il cliente non ha cellulare ed email: avviso e nessun invio.
- **Genera plico** e filtro **Plico dello stato attuale** (§5).
- Semantica di `is_signed`: sul **tipo** = "va firmato"; sul **documento** = "è stato firmato". Non copiare mai l'uno sull'altro.

### 4.2 Verifica al caricamento e coda "Documenti da verificare"
`app/Listeners/VerifyUploadedDocument.php` ascolta `MediaHasBeenAddedEvent` (collezioni `documents` e `signed`) e chiama `app/Services/DocumentVerifier.php`:
- imposta lo stato `caricato` se era `richiesto`, calcola `file_hash` (SHA-256) e salva l'esito in `documents.metadata.verifica` (`signature`, `anomalie`, `otp`, `checked_at`);
- controlli: file mancante/illeggibile, scaduto, duplicato (stesso hash su stesso record e tipo), firma (solo tipi `is_signed`): **otp** (transazione Yousign abbinata e completa), **digitale** (marcatori `/ByteRange` e `/Sig` nel PDF, non validata), **olografa** (da verificare a vista), **otp_in_attesa**/**assente** (anomalia);
- anomalie in `app/Enums/DocumentAnomaly.php`.
Coda (`app/Filament/Resources/DocumentVerifications/`, menu Settings con badge): documenti in stato `caricato`; **Accetta** (`approvato`, `verified_by/at`; per la firma olografa serve la conferma esplicita), **Rifiuta** (`respinto`, anomalia obbligatoria, `rejection_note` = "Anomalia: nota"). "Transazione firma" mostra il riferimento Yousign e i firmatari.

### 4.3 Scadenziario (`app/Filament/Resources/DocumentSchedules/`)
Mostra documenti **assenti** (`richiesto`), **con anomalia** (`respinto`, o `caricato` con anomalie) e **in scadenza**. `DocumentReminderService::scheduleQuery()` ne definisce l'insieme; il comando `documents:sync-schedule` riscrive `document_schedules` (denormalizzata); "Invia solleciti" manda una sola email per destinatario. Per i documenti senza scadenza il sollecito si ripete ogni `documents.missing_reminder_days` giorni (default 7) e lo stato non passa a `provvisorio`. Destinatari: cliente (o cliente collegato alla pratica) in `DocumentRecipientResolver`. Pianificato: `documents:send-reminders` alle 08:00.

## 5. Plichi documentali

Un **plico** è una riga di `tasks` con i suoi tipi documento in `task_document_types` (`is_required`, `slug`). Modello `app/Models/Task.php`, risorsa `Tasks` ("Plico documentale", menu Settings).

Condizioni (`Task::matchesConditions()`): esclusione (`exclude_*`) → condizione principale (`trigger_field/state/value`) → secondo campo (`trigger_subfield = trigger_subvalue`). Confronti testuali **senza distinguere maiuscole**; i booleani valgono `1/0`. `taskable` è l'alias del morph map (`pratica`, `client`, `fornitore`…) o il nome breve del modello.

Generazione:
- `Task::generateDocumentsFor($record)` crea i documenti **mancanti** (stato `richiesto`, mai cancella; `is_signed` non copiato dal tipo).
- Trait `app/Models/Concerns/GeneratesPlichi.php` (su `Pratica` e `Client`): alla creazione e quando cambia un campo che governa un plico (`Task::watchedFieldsFor()`) genera i documenti dei plichi ora applicabili. Non scatta per modifiche fatte fuori da unicoloan (import da Mediafacile, query dirette): usare il pulsante **Genera plico**.
- Dati iniziali: `database/seeders/PlichiPraticaSeeder.php` (idempotente, `php artisan db:seed --class=PlichiPraticaSeeder`) crea un plico per **prodotto × stato** (`pratiches_statos`) con i tipi documento tipici (famiglie: cessione/delega, TFS, prestiti, mutui, corporate, polizza, utenza) e i plichi del cliente per `status` e persona fisica/giuridica. Gli stati sono quelli già presenti in `pratiches_statos` (proforma, modello `PraticaStati`): il plico usa la grafia salvata in quella tabella e salta con un avviso gli stati che non esistono.

## 6. Firma elettronica (OTP con Yousign)

`app/Services/Signature/`: `SignatureRequestService` (invio, riconciliazione, annullo, PDF firmato), provider `YousignSignatureProvider` e `FakeSignatureProvider` (`SIGNATURE_DRIVER`), webhook `POST /api/signature/webhook/{provider}`. Tabelle `signature_requests` e `signature_request_signers`; slot di firma nei `pdf_modules.signature_slots`. `SendForSignatureAction` (`app/Filament/Actions/`) apre il modale con i firmatari precompilati (`SignerDefaults`); a firma completata nasce un nuovo `Document` firmato (collezione `signed`). Comandi: `signature:check`, `signature:sync` (oraria), `signature:fake-complete` (sviluppo).

## 7. KYC / adeguata verifica

`kyc_questionnaires` (+ titolari effettivi). Scheda **KYC (adeguata verifica)** del cliente (`KycQuestionnairesRelationManager`, form condiviso in `Schemas/KycQuestionnaireForm.php`) e scheda **Adeguata verifica** nel portale agenti. Servizi in `app/Services/Kyc/`: `KycQavGenerator` (genera il QAV PDF), `KycApprover`, `BeneficialOwnerSuggester`, `KycModuleValues`. Copertura (`KycCoverage`): mancante / scaduto / completo; `Client::currentKyc()` dà l'ultimo approvato. L'OTP e l'approvazione alla firma sono in `SignatureRequestService::approveSignedKyc()`.

## 8. Moduli PDF

`pdf_modules` / `pdf_module_fields` (risorsa `PdfModules`): ogni campo del PDF è mappato a una chiave dati (`app/Enums/ModuleSourceKey.php`, unica whitelist) o, per le caselle, a una condizione `checkbox_when` (`[!]chiave[=valore]`). `ModuleDataResolver` risolve i valori (cliente, sede, documento d'identità, KYC, pratica, compenso cliente = somma provvigioni tipo "Cliente"); `PdfFormFiller` compila con `pdftk`. Generazione per pratica: `GeneraModuliPraticaAction` + `PraticaModuleGenerator`. Mappati: QAV persona fisica e giuridica, proforma fattura mutuo. Per aggiungere una chiave: un `case` in `ModuleSourceKey`, l'etichetta e il ramo in `ModuleDataResolver::resolveForClient()`.

## 9. Pratiche e portale agenti

- Risorsa `Praticas`: schede Richiedente/coobbligati/garanti, Requisiti operativi, Storico stati, Documenti, Provvigioni. Stati in `pratiches_statos` (modello `PraticaStati`) e storico in `PraticaStatusHistory`.
- Portale `/agenti` (`app/Filament/Agenti/`): l'agente crea e vede solo le **sue** pratiche (`ClientForPraticaCreator`, `AgentAccessService`); schede Adeguata verifica, **Documenti** (carica qualunque tipo, senza modifica/cancellazione) e Documenti da firmare (solo tipi `is_signed`, copia firmata a mano).
- `GET /api/pratiche/{id}`, `GET /api/models/{model}/fields`, `PATCH /api/models/{model}/{id}`, `GET /api/users/lookup` sono consumati da UnicoBPM (oggi senza autenticazione: da aggiungere prima del rilascio).

## 10. Registro chiamate API (`api_calls`)

Risorsa **Chiamate API** (menu Settings). `app/Services/ApiCallLogger.php::record()` scrive: servizio (`ApiProvider`), operazione, record interrogato (morph `subject`), utente, esito (`ApiCallStatus`), HTTP, durata, **costo** (solo se riuscita; unitario da `ApiProvider::unitCost()` o passato esplicitamente), parametri (mai le credenziali), `response` (JSON), riferimento del provider, errore. Per aggiungere un servizio: caso in `ApiProvider`, voce in `config/services.php`, chiamata a `record()` nel servizio.

## 11. Menu, permessi e piani

- Menu admin: **Clienti** e **Pratiche** in cima; tutto il resto nel gruppo **Settings** (`navigationGroup`, e `AdminPanelProvider::navigationGroups()`). Test: `tests/Feature/AdminNavigationTest.php`.
- `HasPlanAccess` (risorse) e `HasRelationPlanAccess` (relation manager) usano `checkPiano()` (`app/helpers.php`): piano (`PlanType`), ruolo admin/super admin, agente escluso, permessi per `EmployeeType` (fail-open se l'utente non ha un profilo employee). Il flag `$shouldRegisterNavigation = false` **non** ha effetto sulle risorse che usano `HasPlanAccess`.

## 12. Comandi e pianificazione

| Comando | Cosa fa |
|---|---|
| `documents:sync-schedule` | riscrive lo scadenziario |
| `documents:send-reminders` | solleciti email (08:00) |
| `signature:sync` / `signature:check` / `signature:fake-complete` | firma elettronica |
| `clients:create-missing` | crea i clienti mancanti dalle pratiche (codice fiscale / P.IVA senza cliente) |
| `modules:sync-fields` / `modules:check-printable` | campi e stampabilità dei moduli PDF |
| `permissions:sync-resources` / `manual:sync` | risorse/abilitazioni e assistente manuale |

## 13. Cose da sapere prima di toccare il codice

1. **Connessione del modello** prima di tutto (§1).
2. **Filament 5**: azioni in `Filament\Actions\*`; le azioni in un campo (`suffixActions`) si testano con `TestAction::make('nome')->schemaComponent('<chiave>', 'form')`, dove la chiave è il nome del campo.
3. Le notifiche e gli avvisi sono in italiano; i test asseriscono sul titolo (`assertNotified('...')`).
4. `is_signed` ha due significati (§4.1); `DocumentStatus` ha `PENDING = 'richiesto'`.
5. I test che creano `Pratica` o `Client` scatenano `GeneratesPlichi`: creare i plichi **dopo** il record se si vuole provare il pulsante "Genera plico".
6. Il grafo della conoscenza è in `graphify-out/` (`graph.html`, `GRAPH_REPORT.md`), ricostruito su `app`, `database`, `routes`, `resources/docs`.

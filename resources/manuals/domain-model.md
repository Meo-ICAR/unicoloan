# Manuale tecnico — Modello di dominio Unicoloan

> Documento di riferimento per chi lavora sul codice (umano o AI). Descrive cosa fa l'app, come sono organizzati i modelli Eloquent, le due connessioni al database e gli errori ricorrenti già incontrati in questo repository.

## 1. Cosa fa l'app

Unicoloan è il gestionale di un **mediatore creditizio**: intermedia prodotti di finanziamento tra banche mandanti e clienti finali, tramite una rete di agenti.

Il flusso di business, in breve:

1. Una **banca** (`Clienti`) mette a disposizione dei **prodotti finanziari** (`Tipoprodotto` → `TipoprodottoSub`), ciascuno con dei **vincoli di fattibilità** (`TipoprodottoSubConstraint`: età, importi, LTV, tipo di impiego ammesso, ecc.).
2. Un **agente** (`Fornitore`) propone questi prodotti a un **cliente finale** (`Client`, persona fisica o società — se società, i rapporti societari tra soci sono in `ClientRelation`).
3. Il cliente firma un **mandato** (`ClientMandate`) e si apre una **pratica di finanziamento** (`PROFORMA\Pratica`), che passa attraverso stati di lavorazione (`PraticaStato`, con storico in `PraticaStatusHistory`) e requisiti da soddisfare (`PraticaRequisito` / `PraticaRequisitoOperativo`).
4. Alla pratica sono allegati **documenti** (`Document`, polimorfico), e vengono calcolate le **provvigioni** (`Provvigione` sulla pratica, `ProvvigioniRule` come regole configurabili per prodotto/banca/agente).
5. Una banca può **bloccare in blacklist** un agente o un dipendente specifico (`BlacklistClienteFornitore`, `BlacklistClienteEmployee`): se attivo, quell'agente/dipendente non dovrebbe essere assegnabile a pratiche di quella banca (`App\Services\BlacklistChecker`).

## 2. Le due connessioni al database — la cosa più importante di questo manuale

L'app scrive su **due database MySQL distinti**, sullo stesso host:

| Connessione | Database | Proprietà | Contenuto |
|---|---|---|---|
| `mysql` (default) | `unicooam` | **Di questa app** | Employee, Document, Company, User, tabelle di workflow interno (pratica_stati, pratica_requisiti, requisito_tipo_finanziamento, provvigioni_rules, tipoprodotto_sub_constraints, client_types, client_relations, client_mandates, lead_sources, resources, task*, email_templates, ecc.) |
| `mysql_proforma` | `proforma` | **Di un'altra app legacy** | `Clienti` (banche), `Fornitore` (agenti), `Pratica`, `Provvigione`, `Client` (clienti finali), `Compenso`, `Tipoprodotto`/`TipoprodottoSub`, blacklist, `PraticaStatusHistory` |

Le due connessioni condividono lo stesso server MySQL, quindi **join cross-database con nome tabella qualificato funzionano** (es. `Clienti::oamCodes()` joina `unicooam.clienti_oam`), ma **non ci sono FK a livello di database tra le due connessioni** — solo relazioni Eloquent.

### ⚠️ Il bug più comune di questo codebase: connessione ereditata per sbaglio

Quando un modello **non dichiara esplicitamente `protected $connection`**, ed è caricato tramite una relazione (`hasMany`/`belongsTo`/ecc.) da un modello che vive sull'**altra** connessione, Eloquent (`Model::newRelatedInstance()`) gli fa **ereditare silenziosamente** la connessione del genitore invece di usare quella di default (`mysql`).

Questo ha causato, solo in questa sessione, errori "Base table or view not found" su: `ProvvigioniRule`, `PraticaRequisito`, `PraticaRequisitoOperativo`, `RequisitoTipoFinanziamento` — tutti modelli su `mysql` ma raggiunti tramite relazioni da modelli `mysql_proforma` (`Pratica`, `TipoprodottoSub`).

**Regola pratica**: ogni modello che vive fuori dalla connessione di default **deve** dichiarare `protected $connection = 'mysql';` (o `'mysql_proforma'`) esplicitamente, anche se sembra ridondante — soprattutto se è raggiungibile tramite una relazione da un modello sull'altra connessione. Non fidarti del fatto che "prima funzionava": funziona solo se il modello è sempre stato interrogato direttamente, mai tramite relazione.

## 3. Inventario dei modelli

### 3.1 Anagrafiche core del business (banche / agenti / clienti)

| Modello | Connessione | Tabella | PK | Note |
|---|---|---|---|---|
| `PROFORMA\Clienti` | mysql_proforma | `clientis` | UUID | La **banca/istituto di credito**. **Non confondere con `Client`** (cliente finale) né con `Cliente` (non esiste, mai usarlo). |
| `PROFORMA\Fornitore` | mysql_proforma | `fornitoris` | UUID | L'**agente** esterno che vende i prodotti. |
| `App\Models\Client` | mysql_proforma | `proforma.clients` | int | Il **cliente finale** (persona fisica o società). Relazione `leadSource()`, `clientMandates()`, `clientPratiches()` (match per `codice_fiscale`/`tax_code`, non FK). |
| `ClientType` | mysql | `client_types` | int | Ruoli/tipologie privacy del cliente (titolare, garante, lead, ecc.). |
| `ClientRelation` | mysql | `client_relations` | int | Composizione societaria: chi è socio/titolare di chi (`company_id` = persona giuridica, `client_id` = persona fisica), con quota (`shares_percentage`). |
| `ClientMandate` | mysql | `client_mandates` | int | Il mandato firmato dal cliente (numero, date, importo, stato). |
| `LeadSource` | mysql | `lead_sources` | int | Sorgente di provenienza di un lead (call center, sito, referral, ecc.). |
| `Employee` | mysql | `employees` | int | Dipendente interno del mediatore. |
| `EmployeeType` | mysql | `employee_types` | int | Ruolo/tipo di dipendente, con permessi (`EmployeeTypePermission`). |
| `FornitoriRole` | mysql_proforma | `proforma.fornitoriroles` | int | Livello/ruolo dell'agente (Junior, Senior, ecc.), usato in `ProvvigioniRule.kind_id`. |
| `Company` | mysql | `companies` | UUID | L'azienda mediatrice stessa (tenant). |
| `Branch` | mysql | `branches` | int | Sede/filiale, polimorfica (`branchable`: Company o Fornitore). |

### 3.2 Blacklist banca ↔ agente/dipendente

| Modello | Connessione | Tabella | Note |
|---|---|---|---|
| `BlacklistClienteFornitore` | mysql_proforma | `blacklist_clienti_fornitori` | Blocco di un **agente** da parte di una **banca** (`cliente_id`, `fornitore_id`, `motivo`, `data_inizio`/`data_fine`, scope `attivi()`). |
| `BlacklistClienteEmployee` | mysql_proforma | `blacklist_clienti_employees` | Blocco di un **dipendente** da parte di una banca. `employee_id` non ha FK di database (Employee vive su `mysql`). |

Relazioni dirette sui modelli principali:
- `Fornitore::blacklistRecords()` (hasMany → `BlacklistClienteFornitore`) e `Fornitore::isBlacklistedBy($clienteId)`.
- `Clienti::blacklistRecords()` (hasMany → `BlacklistClienteFornitore`) e `Clienti::dipendentiBlacklistati()` (hasMany → `BlacklistClienteEmployee`).
- `Employee::bancheBlacklist()` (hasMany → `BlacklistClienteEmployee`) e `Employee::isBlacklistedBy($clienteId)`.
- `App\Services\BlacklistChecker`: centralizza il controllo "questo agente/dipendente può lavorare con questa banca?". Oggi è agganciato solo al campo agente di `PraticaForm` — **non esiste ancora un hook per il lato dipendente** (`Pratica` non ha colonna `employee_id`).

### 3.3 Catalogo prodotti finanziari

| Modello | Connessione | Tabella | Note |
|---|---|---|---|
| `Tipoprodotto` | mysql_proforma | `proforma.tipoprodotto` | Macro-categoria di prodotto (Mutuo, Cessione del Quinto, Prestito, ecc.). Ha **sia** `name` **sia** `tipo_prodotto` (due colonne testo distinte, entrambe popolate — `tipo_prodotto` è quella usata su `pratiches.tipo_prodotto`). |
| `TipoprodottoSub` | mysql_proforma | `proforma.tipoprodotto_sub` | Sottoprodotto specifico (es. "Mutuo Acquisto", "CQS Privati"), identificato in modo stabile dal campo `code` (univoco) — **non fare mai affidamento sull'`id` auto-increment per riferimenti fissi**, dipende dall'ordine di inserimento. |
| `TipoprodottoSubConstraint` | mysql | `tipoprodotto_sub_constraints` | Vincoli di fattibilità (età, importo, durata, LTV, rapporto rata/reddito, tipi di impiego ammessi in JSON) per un prodotto/sottoprodotto **imposti da una banca specifica** (`clienti_id` è `NOT NULL` — un vincolo appartiene sempre a una banca). |
| `RequisitoTipoFinanziamento` | mysql | `requisito_tipo_finanziamento` | Associa un `PraticaRequisito` (task/documento richiesto) a un tipo/sottotipo prodotto, con flag obbligatorio/ordine. |
| `ProvvigioniRule` | mysql | `provvigioni_rules` | Regola di calcolo provvigione, a cascata Prodotto → Sottoprodotto → Banca → Ruolo agente (`kind_id`) → Agente specifico (`fornitori_id`). Se un campo è `NULL` la regola si applica più in generale. |
| `Compenso` | mysql_proforma | `compensos` | Anagrafica degli stati possibili di un compenso/provvigione (`status_compenso`, chiave primaria stringa), referenziata da `Provvigione.status_compenso`. |

### 3.4 Pratica e workflow

| Modello | Connessione | Tabella | Note |
|---|---|---|---|
| `PROFORMA\Pratica` | mysql_proforma | `pratiches` | La pratica di finanziamento. Collegata a banca/agente **per stringa denormalizzata** (`denominazione_banca`/`denominazione_agente`), risolta tramite `istituto()`/`agente()` — non FK numeriche. |
| `PraticaStato` | mysql | `pratica_stati` | Stati del **workflow interno** di lavorazione di una pratica (bozza, richiesto, approvato…), con transizioni ammesse in `pratica_stati_transizioni`. |
| `PraticaStati` (plurale, modello diverso!) | mysql (ma tabella qualificata `proforma.pratiches_statos`) | — | Valori **distinti dello stato legacy** della tabella `pratiches` stessa (`stato_pratica`: "SOSPESA", "PERFEZIONATA", ecc.). **Non è lo stesso concetto di `PraticaStato`** — sono due workflow diversi che coesistono. |
| `PraticaStatusHistory` | mysql_proforma | `proforma.pratica_status_history` | Log storico dei cambi di stato di una pratica. |
| `PraticaRequisito` | mysql | `pratica_requisiti` | Catalogo dei requisiti/documenti richiedibili (es. "certificato_stipendio"). |
| `PraticaRequisitoOperativo` | mysql | `pratica_requisiti_operativi` | Istanza operativa di un requisito su una pratica specifica (stato: da_richiedere/richiesto/approvato). |
| `PraticaDocumentRequest` | mysql_proforma | `proforma.pratica_document_requests` | Richiesta di un documento specifico su una pratica. |
| `Provvigione` | mysql_proforma | `provvigioni` | Il compenso calcolato/registrato per una specifica pratica. |

### 3.5 Documenti, comunicazioni, sistema

| Modello | Connessione | Tabella | Note |
|---|---|---|---|
| `Document` | mysql | `documents` | Allegato **polimorfico** (`documentable`: Pratica, Clienti, Fornitore, Client, Employee, Company...). Backed by Spatie Media Library. |
| `DocumentType`, `DocumentReminder`, `DocumentSchedule` | mysql | — | Tipologie documento, promemoria di scadenza, pianificazione ricorrente. |
| `Task`, `TaskDocumentType` | mysql | — | Task interni, polimorfici (`taskable`), con gerarchia parent/children. |
| `EmailTemplate`, `MailAccount` | mysql | — | Template email e account SMTP configurabili. |
| `Organization` | mysql | `organizations` | Organismo di vigilanza esterno (OAM, IVASS). |
| `Resource`, `EmployeeTypePermission` | mysql | — | Motore RBAC: `Resource` = modulo/permesso disponibile, `EmployeeTypePermission` = permesso assegnato a un tipo di dipendente. |
| `User`, `SocialiteUser` | mysql | — | Utenti del pannello Filament e login social (Microsoft/Google). |
| `Website`, `ClientiOam` | mysql | — | Siti web collegati (polimorfico), codici OAM associati a una banca. |

## 4. Convenzioni deliberate (non sono bug)

- **Relazioni per stringa denormalizzata**: `Pratica::istituto()`/`agente()` fanno match su `denominazione_banca`/`denominazione_agente` = `name`, non su FK numeriche. È una scelta esistente della tabella legacy `pratiches`, condivisa con l'app PROFORMA esterna — non "correggerla" isolatamente.
- **Nessuna FK a livello di database tra le due connessioni**: quando serve un riferimento cross-connessione (es. `BlacklistClienteEmployee.employee_id`, `client_mandates.client_id`), si usa una colonna semplice + relazione Eloquent, mai `->constrained()`/`->foreign()`.
- **`TipoprodottoSub.code` come chiave logica stabile**: i seeder di catalogo (`TipoProdottoSubSeeder`, `TipoProdottoSubConstraintsSeeder`) risolvono i sottoprodotti per `code`, non per `id`, proprio perché l'`id` non è garantito stabile tra ambienti.

## 5. Insidie note (errori già presi in questo repo — occhio a non ripeterli)

1. **Import mancante → risoluzione nel namespace sbagliato**: se un file in `App\Models\PROFORMA\X` usa `Y::class` senza `use App\Models\Y;`, PHP lo risolve come `App\Models\PROFORMA\Y` (che spesso non esiste) → `Class not found` **solo quando la relazione viene effettivamente chiamata**, non prima. Successo più volte con `PraticaRequisito`, `PraticaRequisitoOperativo`, `PraticaStatusHistory`, `OamCode` dentro `Pratica.php`.
2. **Nomi di classe quasi identici**: `Client` ≠ `Clienti` ≠ `Cliente` (quest'ultimo non esiste, mai usarlo); `PraticaStato` ≠ `PraticaStati`. Verificare sempre quale dei due si intende.
3. **Colonna del modello disallineata dalla migration reale**: es. `ProvvigioniRule` aveva `fornitorirole_id` nel `$fillable`/relazione mentre la colonna reale è `kind_id`. Prima di aggiungere/usare un campo, controllare lo schema reale (`Schema::getColumns($table)`), non fidarsi del `$fillable`.
4. **`->pluck('label', 'key')` su colonne nullable usato come opzioni di un `Select` Filament**: se una riga ha la colonna-label `NULL`, Filament va in `TypeError` su `isOptionDisabled()`. Filtrare sempre con `whereNotNull(...)` prima del pluck quando la colonna può essere nulla.
5. **Query builder riutilizzato in un loop con `->where(...)`**: `$query->where(...)` non è idempotente su un'istanza condivisa — le condizioni **si accumulano** ad ogni chiamata invece di sostituirsi. In un ciclo di seeder "salta se esiste già", questo rende il controllo sempre più falso positivo a ogni iterazione. Costruire una query fresca ad ogni iterazione del loop.
6. **Migrazioni con guardia sulla connessione sbagliata**: `Schema::hasTable('clients')` controlla la connessione di **default**, non `mysql_proforma` dove `clients` vive davvero — la guardia risultava sempre vera (tabella "non esiste") e la migration restava permanentemente no-op.
7. **File/feature cancellati manualmente lasciano riferimenti pendenti**: quando un modello o una migration viene rimossa a mano (fuori da una migration `down()`), tutto ciò che la referenzia (widget, resource Filament, seeder, test, `Relation::morphMap`) resta rotto silenziosamente finché non viene effettivamente eseguito. Dopo ogni rimozione, cercare `grep -rn NomeClasse app database tests`.
8. **Codice generato ma mai davvero collegato**: questo repo ha accumulato più volte funzionalità "quasi complete" (l'intero modulo di import/reportistica OAM semestrale, azioni Filament orfane) mai raggiungibili da nessuna UI/comando reale. Prima di "riparare" qualcosa che sembra rotto, verificare con `grep` se è davvero raggiungibile: se non lo è, è più sensato eliminarlo che ripararlo.

## 6. Per il prossimo "vibe coding" — contesto da incollare a inizio sessione

> Questa app (Unicoloan) gestisce pratiche di finanziamento per un mediatore creditizio. Entità chiave: `Pratica` (mysql_proforma) = pratica di finanziamento; `Client` (mysql_proforma, tabella `proforma.clients`) = cliente finale, persona fisica o società — se società, i soci/titolari sono in `ClientRelation` (mysql); `PROFORMA\Clienti` (mysql_proforma, tabella `clientis`) = la **banca** mandante, non confondere con `Client`; `PROFORMA\Fornitore` (mysql_proforma, tabella `fornitoris`) = l'**agente** che vende i prodotti. I prodotti sono `Tipoprodotto`/`TipoprodottoSub` (mysql_proforma) con vincoli di fattibilità in `TipoprodottoSubConstraint` (mysql, per banca). Le provvigioni sono regolate da `ProvvigioniRule` (mysql) e registrate per pratica in `Provvigione` (mysql_proforma). Una banca può bloccare un agente/dipendente specifico: `BlacklistClienteFornitore`/`BlacklistClienteEmployee` (mysql_proforma), controllati da `App\Services\BlacklistChecker`.
>
> **L'app scrive su due database MySQL distinti** (connessioni Laravel `mysql` = proprio db `unicooam`, `mysql_proforma` = db legacy `proforma` di un'altra app, stesso host). **Ogni modello che vive fuori dalla connessione di default deve dichiarare esplicitamente `protected $connection`**, altrimenti quando viene caricato tramite una relazione da un modello sull'altra connessione, eredita silenziosamente quella sbagliata (bug ricorrente in questo repo, causa più frequente di "Base table or view not found"). Prima di aggiungere una relazione tra un modello mysql e uno mysql_proforma, verificare la connessione dichiarata di entrambi.
>
> Prima di usare/estendere un campo o una relazione, verificare lo schema reale con `Schema::getColumns($table)` (o `Schema::connection('mysql_proforma')->getColumns($table)`), perché `$fillable` e i nomi delle relazioni nel codice a volte sono disallineati dalla colonna reale (es. rinominata in una migration successiva senza aggiornare il modello). Prima di "riparare" un errore che coinvolge una classe/risorsa che sembra mancante, controllare con `grep -rn NomeClasse app database tests` se è raggiungibile da qualcosa di reale: questo repo ha diverso codice generato ma mai collegato a un comando/UI reale, spesso più sensato da eliminare che da riparare.
>
> `App\Models\CompanyRole` (mysql, tabella `company_roles`) non è solo un ruolo interno: è usato anche da altre procedure collegate (es. gestione privacy) per indicare il **tipo di azienda** del mediatore stesso — non solo "FINANCE", ma anche ad es. "Call Center" o "Hotel". Tienilo presente prima di assumere che `CompanyRole` riguardi solo ruoli/audit interni.

## 7. File di riferimento

- `app/Services/BlacklistChecker.php` — logica di blocco banca↔agente/dipendente.
- `app/Support/DocumentRecipientResolver.php` — risoluzione destinatario/etichetta per un documento allegato polimorficamente.
- `config/database.php` — definizione delle due connessioni (`mysql`, `mysql_proforma`).
- `database/seeders/` — seeder di catalogo (`PraticaStatiSeeder`, `PraticaRequisitiSeeder`, `TipoProdottoSeeder`, `TipoProdottoSubSeeder`, `TipoProdottoSubConstraintsSeeder`, `ProvvigioniRuleSeeder`, `ClientTypeSeeder`, `LeadSourceSeeder`) — nessuno di questi è registrato in `DatabaseSeeder`, vanno lanciati singolarmente con `php artisan db:seed --class=NomeSeeder`.

# Questionario KYC (Adeguata Verifica) — Design (rev. 2)

Rev. 2 del 2026-10-09: il questionario ricalca i moduli **QAV** ufficiali già presenti in `storage/app/public/module` (QAV Persona fisica 06/2025, QAV Persona giuridica 06/2025); la scadenza è gestita dal catalogo `DocumentType`.

## Obiettivo
Il mediatore creditizio compila e conserva, per ogni cliente, il questionario di adeguata verifica (D.Lgs. 231/2007) con le **stesse risposte a scelta fissa dei QAV**, per le persone giuridiche anche esecutore e titolari effettivi, e lo stampa sul QAV ufficiale con il sistema `PdfModule`. Si vede per ogni cliente se il KYC è mancante, scaduto o completo.

## Decisioni
- Il questionario nell'app = le domande del QAV (enum a scelta fissa, non testo libero). Stampa = `PdfModule` sui due QAV.
- Tabelle separate legate al **cliente** (`client_id`); `client_mandate_id` e `pratica_id` nullable come riferimento.
- Una compilazione = un record; nessuna sovrascrittura (storico).
- **Scadenza via `DocumentType`**: all'approvazione si genera il QAV PDF come `Document` del cliente con `document_type_id` del tipo QAV (persona fisica / giuridica). `expires_at` lo calcola `DocumentType::durationCalculate()` e i promemoria sono quelli già esistenti (`DocumentReminder`). Niente `next_review_at` né mesi per livello di rischio: la durata è quella del tipo (es. 12 mesi, configurabile dal catalogo). Il `risk_level` resta un'informazione dell'operatore.
- Copertura KYC del cliente: `mancante` = nessun questionario approvato con documento; `scaduto` = `expires_at` del documento dell'ultimo approvato nel passato; `completo` altrimenti (documento senza scadenza = non scade).
- Fase 1 **solo informativo** (badge/filtri/avvisi); nessun blocco delle pratiche.
- Pregresso: nessun backfill. Clienti senza KYC = "mancante", filtro in lista.
- Il firmatario elettronico ({{Sig1_ev_…}} nei QAV) è fuori ambito.

## Vincoli tecnici
- `Client` e `Pratica` stanno su `mysql_proforma`: `client_id` = `unsignedBigInteger`, `pratica_id` = `string(64)`, niente FK native né subquery cross-connection (`pluck()` + `whereIn`). Le nuove tabelle stanno su `mysql`.
- Soglia titolare effettivo: quota **> 25%** (art. 20 D.Lgs. 231/2007).
- `pdftk` non è installato in questo ambiente di sviluppo: i test che richiedono la compilazione reale si saltano; i nomi dei campi AcroForm dei QAV sono stati letti dal file.

## Struttura dei QAV (verificata)
**Persona fisica** — campi checkbox `1a…8c` = numero domanda + lettera in ordine di apparizione (conteggi coincidenti col testo): 1 PEP (5), 2 attività economica (6), 3 settore (11: a–i, l, m), 4 localizzazione (5), 5 natura del finanziamento (4), 6 finalità (2), 7 reddito annuo (4), 8 patrimonio (3); più anagrafica (Text*, `data`, `luogo`).
**Persona giuridica** — `1a-e` natura giuridica (5), `2a-c` area geografica (3), `3a-3i,3l` scopo del finanziamento (10), `4a-b` legame cliente/esecutore (2), `5a-e` PEP esecutore, poi per ciascuno di **3 titolari effettivi**: criterio di individuazione (5) e PEP (5) (TE1 = 6/7, TE2 = 8/9, TE3 = 10/11); più anagrafica di esecutore e di ogni titolare (Text*, `dummyFieldName*`).
I nomi dei campi di testo sono generici: la mappatura campo→chiave si fa dalla UI con il PDF diagnostico (`modules:sync-fields --diagnostic`), dove c'è `pdftk`. Le caselle 1a…11e si mappano per convenzione numero+lettera (da verificare sul diagnostico).

## Dati
`kyc_questionnaires` (tutti i valori enum come stringhe):
- riferimenti: `client_id`, `client_mandate_id`?, `pratica_id`?, `document_id`? (il QAV generato);
- comuni: `pep_status` (5 valori QAV), `financing_purpose` (persona fisica: 2 valori; giuridica: 10), `risk_level` (basso/medio/alto), `status` (bozza/completo/approvato), `compiled_at`, `verified_by`, `verified_at`, `notes`;
- persona fisica: `economic_activity` (6), `activity_sector` (11), `activity_location` (5), `financing_nature` (4), `income_band` (4), `wealth_band` (3), `acts_for_third_party` bool (se vero, serve titolare effettivo);
- persona giuridica: `legal_nature` (5), `geographic_area` (3), `executor_client_id`, `executor_link` (2: rappresentante legale / delegato), `executor_pep_status` (5).

`kyc_beneficial_owners`: `kyc_questionnaire_id` (FK, cascade), `position` (1–3, ordine nel QAV), `client_id` (persona), `shares_percentage`, `control_criterion` (5 valori QAV: quota >25% / maggioranza voti / influenza dominante voti / influenza dominante contratti / poteri di amministrazione-rappresentanza), `pep_status` (5), `declaration_signed_at`, `document_id`?, `is_verified`. Il QAV stampa al massimo 3 titolari.

Enum (stile `AmlReportStatus`, `HasLabel`, etichette = testo del QAV): `KycPepStatus`, `KycEconomicActivity`, `KycActivitySector`, `KycActivityLocation`, `KycFinancingNature`, `KycPersonPurpose`, `KycCompanyPurpose`, `KycIncomeBand`, `KycWealthBand`, `KycLegalNature`, `KycGeographicArea`, `KycExecutorLink`, `KycControlCriterion`, `KycRiskLevel`, `KycStatus`, `KycCoverage`.

## Regole
- Persona fisica: sezione base; il titolare effettivo serve solo se `acts_for_third_party`.
- Persona giuridica: dati società + esecutore + 1–3 titolari effettivi. "Precompila da cariche sociali" crea le righe da `client_relations` con quota > 25% e carica attiva; se nessuno supera la soglia, riga residuale (criterio "poteri di amministrazione/rappresentanza") col rappresentante legale; più di 3 titolari → avviso (il QAV ne stampa 3).
- Completo/approvabile solo con tutte le risposte obbligatorie del tipo di cliente; per le società serve ≥1 titolare effettivo e tutti verificati.
- Approvazione = stato `approvato` + generazione del QAV PDF come `Document` del cliente (`document_type_id` del tipo QAV, `emitted_at` = data approvazione). Se il tipo QAV non è collegato al modulo PDF, l'approvazione fallisce con messaggio chiaro.

## Integrazione con il sistema moduli PDF
- `ModuleSourceKey`: nuove chiavi `kyc.*` (una per risposta: `kyc.pep_status`, `kyc.economic_activity`, … `kyc.executor.*`, `kyc.owner1.*`/`owner2`/`owner3` per anagrafica, criterio, PEP).
- `checkbox_when` supporta l'uguaglianza `chiave=valore` (es. `kyc.pep_status=nessuna`) oltre al booleano e alla negazione `!`; così una casella per opzione non richiede una chiave per opzione.
- `ModuleDataResolver` risolve le chiavi `kyc.*` dal KYC più recente del cliente (approvato, altrimenti l'ultima bozza). Nuovo punto d'ingresso per il solo cliente (senza `Pratica`) per stampare dal KYC; il flusso pratica continua a funzionare e compila i QAV con l'ultimo KYC disponibile.
- Collegamento `PdfModule` ↔ `DocumentType` già disponibile (`document_type_id`, comando `modules:sync-fields --link-document-types`): i due QAV si collegano a due tipi QAV, che portano durata e promemoria.
- Il generatore di moduli oggi lavora per pratica: si estrae l'archiviazione come `Document` in modo da poterla usare anche per il cliente.

## UI (Filament v5)
- `KycQuestionnairesRelationManager` su `ClientResource`: form a sezioni che cambia con `is_person` (sezioni QAV come Radio/Select), titolari in Repeater (max 3), azione "Precompila da cariche sociali", azioni "Approva" e "Stampa QAV".
- Colonna + filtro "KYC" in `ClientsTable`; badge nel tab "Compliance AML"; avviso testuale sulla pratica se il KYC è mancante/scaduto.
- Nessun gating per piano (nessuna feature key esistente).

## Test (PHPUnit)
Copertura (mancante/scaduto/completo) da `Document.expires_at`; precompilazione titolari (soglia >25%, 25.00 escluso, ruoli terminati, residuale); requisiti per persona fisica/giuridica; `checkbox_when` con uguaglianza; chiavi `kyc.*` nel resolver; approvazione che genera il `Document` col tipo QAV (con `PdfFormFiller` mockato dove manca `pdftk`); relation manager; filtro lista; avviso pratica.

## Fuori scopo
Blocco pratiche, firma elettronica, backfill automatico, più di 3 titolari sul modulo, calcolo automatico del rischio.

# Questionario KYC (Adeguata Verifica) — Design

## Obiettivo
Il mediatore creditizio compila e conserva, per ogni cliente, il questionario di adeguata verifica (D.Lgs. 231/2007) e, per le persone giuridiche, la verifica della dichiarazione del Titolare Effettivo. Si vede per ogni cliente se il KYC è mancante, scaduto o completo, chi lo ha verificato e quando.

## Decisioni
- Modulo strutturato salvato in tabella (non PDF).
- Tabella separata legata al **cliente** (`client_id`); `client_mandate_id` e `pratica_id` nullable come riferimento al momento della compilazione.
- Una compilazione = un record. Nessuna sovrascrittura: storico conservato. Il KYC "corrente" è l'ultimo record `approvato` del cliente.
- Fase 1 **solo informativo** (badge/filtri/avvisi). Nessun blocco delle pratiche.
- Pregresso: nessun backfill. Clienti senza record = "KYC mancante"; si lavorano con il filtro in lista.

## Vincolo tecnico
`Client` vive sulla connessione `mysql_proforma` (`proforma.clients`), come `Pratica`. Le nuove tabelle stanno sulla connessione di default; `client_id` / `pratica_id` sono `unsignedInteger` **senza FK nativa** (stesso approccio di `client_relations`). `client_mandate_id` può avere FK se `client_mandates` è sulla stessa connessione (da verificare in implementazione).

## Dati
`kyc_questionnaires`: `client_id`, `client_mandate_id`?, `pratica_id`?, scopo e natura del rapporto, origine fondi/patrimonio, professione/attività, fonte di reddito, operatività a distanza, PEP, paesi a rischio, `risk_level` (basso/medio/alto, enum), `status` (bozza/completo/approvato, enum), `compiled_at`, `verified_by`, `verified_at`, `next_review_at`, timestamps.

`kyc_beneficial_owners`: `kyc_questionnaire_id` (FK, cascade), `client_id` (persona), `shares_percentage`, `control_type` (quote/controllo/residuale, enum), `declaration_signed_at`, `document_id`?, `is_verified`.

Rischio scelto dall'operatore. `next_review_at` precompilata dal rischio: alto +6 mesi, medio +12, basso +36 (modificabile).

## Regole
- Persona fisica: solo sezione base.
- Persona giuridica: sezione base + titolari effettivi. Azione "Precompila da cariche sociali": crea righe da `client_relations` con quota ≥ 25%; se nessuno raggiunge la soglia, riga residuale con il rappresentante legale.
- Stato `completo` solo con sezioni obbligatorie compilate; per le società serve almeno un titolare effettivo verificato.
- Stato derivato sul cliente: `mancante` (nessun approvato), `scaduto` (`next_review_at` passata), `completo`.

## UI (Filament v5)
- `KycQuestionnairesRelationManager` su `ClientResource` (sostituisce il commentato `ChecklistsRelationManager`); form a sezioni, titolari in Repeater/relation manager.
- Colonna + filtro "KYC" in `ClientsTable`; badge nel tab "Compliance AML".
- Avviso testuale sulla pratica se il KYC del cliente è mancante/scaduto.

## Test (PHPUnit)
Stato derivato (mancante/scaduto/completo); precompilazione con soglia 25% e caso residuale; regole persona fisica vs giuridica; nuova versione senza sovrascrittura; filtro in lista.

## Fuori scopo
Blocco pratiche, PDF firmabile, backfill automatico.

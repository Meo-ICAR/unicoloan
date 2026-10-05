# Specifiche Funzionali — Unicooam
## Piattaforma Gestionale per Mediatori Creditizi

**Versione:** 1.0 | **Data:** Giugno 2026 | **Classificazione:** Uso Interno

---

## 1. Panoramica Commerciale

### 1.1 Contesto di Mercato

I mediatori creditizi iscritti all'OAM (Organismo Agenti e Mediatori) sono soggetti a obblighi di segnalazione periodica verso l'Organismo di vigilanza. La principale scadenza è la **Relazione Semestrale OAM**, un documento Excel strutturato in 5 sezioni che deve essere compilato e trasmesso due volte l'anno (entro il 31 luglio per il I semestre e entro il 31 gennaio per il II semestre).

La compilazione manuale di questo report è oggi un processo laborioso, soggetto a errori, che richiede l'estrazione e il riconcilio di dati da fonti diverse: il gestionale commerciale delle pratiche, i registri di compliance, i dati anagrafici societari.

### 1.2 Proposta di Valore

**Unicooam** è un'applicazione gestionale web che centralizza tutti i dati operativi, di compliance e anagrafici di un mediatore creditizio, con l'obiettivo primario di **automatizzare la generazione della Relazione Semestrale OAM** e di supportare la gestione quotidiana delle attività di compliance.

### 1.3 Utenti Target

| Profilo | Ruolo | Utilizzo Principale |
|---------|-------|---------------------|
| Responsabile Compliance | Utente primario | Gestione audit, reclami, SOS, generazione report OAM |
| Amministrativo | Utente secondario | Gestione anagrafiche, documenti, scadenze |
| Direzione | Utente terziario | Visualizzazione dashboard, export report |

### 1.4 Benefici Attesi

- **Risparmio di tempo:** riduzione da 2-3 giorni a poche ore per la preparazione del report OAM semestrale
- **Riduzione errori:** eliminazione del trasferimento manuale di dati tra sistemi
- **Audit trail:** storico completo di tutte le attività di compliance
- **Conformità normativa:** supporto diretto agli adempimenti OAM, AML (antiriciclaggio), gestione reclami

---

## 2. Architettura del Sistema

### 2.1 Stack Tecnologico

| Componente | Tecnologia | Versione |
|------------|------------|---------|
| Framework backend | Laravel | 13 |
| Pannello amministrativo | Filament | 5 |
| Database principale | SQLite / MySQL | — |
| Database esterno (sola lettura) | MySQL (`mysql_proforma`) | — |
| Frontend componenti | Livewire | 4 |
| Gestione file | Spatie Media Library | — |
| Export Excel | pxlrbt/filament-excel | 3 |
| Autenticazione SSO | dutchcodingcompany/filament-socialite | — |

### 2.2 Struttura dei Database

Il sistema utilizza **due connessioni database distinte**:

**Database principale (unicooam):** Contiene tutti i dati gestiti dall'applicazione — anagrafiche societarie, compliance, documenti, configurazioni OAM.

**Database esterno (mysql_proforma):** Sola lettura. Contiene i dati operativi del gestionale commerciale esistente — pratiche, provvigioni, agenti (Fornitore), banche (Clienti). Non viene scritto da Unicooam.

---

## 3. Moduli Funzionali

### 3.1 Modulo Anagrafica Societaria

**Scopo:** Gestire le informazioni della società di mediazione creditizia.

**Entità principali:**

- **Company** — Dati societari: ragione sociale, P.IVA/C.F., numero iscrizione OAM (M510), numero iscrizione RUI IVASS, tipo societario, intestazioni per i report. È il *tenant* unico del sistema.
- **Branch** — Sedi fisiche della società: indirizzo completo (via, numero civico, città, CAP, provincia, regione), responsabile (nome, cognome, C.F.), flag sede principale, date apertura/chiusura. Relazione polimorfica: può appartenere alla Company o a un agente (Fornitore).
- **Employee** — Dipendenti interni: dati anagrafici, ruolo, iscrizioni OAM/RUI, sede assegnata, gerarchia organizzativa, dati GDPR per il registro trattamenti.
- **Website** — Siti web aziendali: dominio, tipo (istituzionale/social), date aggiornamento privacy e trasparenza, flag conformità.
- **MailAccount** — Account email aziendali: configurazione IMAP/SMTP, credenziali cifrate nel DB, flag PEC/attivo.

**Funzionalità Filament:**
- CRUD completo per tutte le entità
- RelationManager per Branch, Website, MailAccount direttamente dalla scheda Company
- RelationManager per dipendenti subordinati dalla scheda Employee

---

### 3.2 Modulo Compliance & Audit

**Scopo:** Tracciare tutte le attività di controllo, ispezione e rilievo a cui è soggetta la società.

**Entità principali:**

- **Audit** — Attività di audit interno o ispezione: tipo (documentale/onsite), autorità ispettrice, oggetto dell'audit (polimorfico: rete agenti, banca mandante, organismo), date pianificate/effettive, stato (Pianificato → In Corso → In Attesa Follow-up → Completato), esito, note.
- **AuditFinding** — Rilievi emersi da un audit: gravità (Info/Minor/Major/Critical), descrizione non conformità, azioni correttive richieste, scadenze, stato (Aperto/In Lavorazione/Risolto/Chiuso/Rischio Accettato). Appartiene a un Audit (1:N).
- **CompanyInspection** — Ispezioni ufficiali ricevute da autorità esterne (OAM, Banca d'Italia, ecc.): nome ispezione, periodo (dal/al), metodo esecuzione, nome ispettore, numerazione progressiva.
- **Remediation** — Catalogo delle tipologie di azioni correttive standard: tipo (AML, Privacy, Reclami, Rete, Trasparenza, Organizzativo), nome, timeframe di risoluzione in ore, livello urgenza calcolato automaticamente.

---

### 3.3 Modulo Gestione Reclami

**Scopo:** Registro e tracciamento di tutti i reclami ricevuti dalla società.

**Entità principali:**

- **ComplaintRegistry** — Registro reclami: numero protocollo, data ricezione, canale, tipo reclamante, macro-categoria e categoria, agente e banca coinvolti, impatto finanziario, stato, scadenza legale, note di risoluzione.

**Logica di business:**
- Alert visivo per reclami scaduti senza risoluzione
- Il conteggio dei reclami ricevuti nel semestre alimenta il foglio 3 del report OAM

---

### 3.4 Modulo Antiriciclaggio (AML)

**Scopo:** Tracciare le Segnalazioni di Operazioni Sospette (SOS).

**Entità principali:**

- **SuspiciousActivityReport** — SOS effettuate: tipo segnalatore, codici anomalie (array), descrizione cifrata nel DB, stato, data segnalazione.

---

### 3.5 Modulo Formazione

**Scopo:** Tracciare i corsi di formazione obbligatori.

**Entità principali:**

- **TrainingRecord** — Registro formazione: framework normativo, titolo corso, provider, modalità, date, ore, esito, certificato. Relazione polimorfica su Employee o Fornitore.

---

### 3.6 Modulo Gestione Documentale

**Scopo:** Archiviazione e tracciamento di tutti i documenti aziendali.

**Entità principali:**

- **DocumentType** — Catalogo tipologie documento.
- **Document** — Record documento con collegamento polimorfico, stato, metadati, hash file. File fisico gestito da Spatie Media Library.

---

### 3.7 Modulo Gestione Task e Scadenze

**Entità principali:**

- **Task** — Attività con nome e descrizione.
- **TaskDocumentType** (pivot) — Associazione Task ↔ DocumentType con flag `is_required`.

---

### 3.8 Modulo OAM — Configurazione e Trascodifica

**Entità principali:**

- **OamCode** — Mappa `tipo_prodotto` → `code` OAM + `description`. Relazione N:M con Clienti via pivot `clienti_oam`.
- **ClientiOam** — Pivot banca mandante ↔ codice OAM con date validità.

---

### 3.9 Modulo OAM — Importazione e Aggregazione Dati

**Flusso di elaborazione:**

```
Pratica (DB esterno)
    ↓ ImportPraticheService
OamPratiche (DB principale) — riga per pratica
    ↓ OamSemestraleService
OamSemestrale (DB principale) — aggregato per prodotto/periodo
    ↓ OamSemestraleExport
Report Excel (.xlsx) — 5 fogli OAM
```

**Parametri di input:** Anno + Semestre (1° = gen-giu, 2° = lug-dic)

---

### 3.10 Modulo OAM — Export Relazione Semestrale

**Struttura del file generato — 5 fogli:**

**Foglio 1 — ANAGRAFICA**

| Campo OAM | Fonte dati |
|-----------|-----------|
| MA1 — Ragione Sociale | `Company.name` |
| MA2 — C.F./P.IVA | `Company.vat_number` |
| MA3 — Periodo di rilevazione | Parametro semestre selezionato |
| MA4A — N. Dipendenti | `Employee.count()` attivi |
| MA4B — N. Collaboratori | `Fornitore.count()` attivi |
| MA5 — N. Sedi Territoriali | `Branch.count()` |
| MA6 — N. progressivo segnalazione | parametro |
| Numero iscrizione M510 | `Company.oam` |

**Foglio 2 — PROFILO ECONOMICO/OPERATIVO BASE**

Righe: MPEB1=Mutui, MPEB2=CQS/CQP, MPEB3=TFS, MPEB4=Credito personale, MPEB5=Segnalazione mutuo

Colonne: intermediari_conv/non-conv, pratiche_intermediate, pratiche_lavorazione, erogato_lordo, erogato_lavorazione, provv_clientela, provv_istituto_comp, premi_istituto_comp, payin_ass_banche/broker/broker_cap, payout_rete_credito, payout_rete_ass_banche/broker/broker_cap, num_rivalse, importo_retrocesse

**Foglio 3 — PROFILO PRUDENZIALE**

| Campo OAM | Fonte dati |
|-----------|-----------|
| MPP1 — N. accessi ispettivi programmati | `CompanyInspection.count()` |
| MPP2 — N. accessi ispettivi effettuati | `CompanyInspection` con data effettiva |
| MPP3 — N. audit programmati | `Audit` Planned nel semestre |
| MPP4 — N. audit eseguiti | `Audit` Completed nel semestre |
| MPP5 — N. SOS effettuate | `SuspiciousActivityReport.count()` |
| MPP6 — N. reclami ricevuti | `ComplaintRegistry.count()` |
| MPP7-9 — Rilievi/Azioni rimedio | `AuditFinding` con auditor, data, rilievo, rimedio |

**Foglio 4 — PROFILO INFORMATIVO E DI TRASPARENZA**

| Campo OAM | Fonte dati |
|-----------|-----------|
| MPI1 — N. siti web | `Website.count()` dove `is_typical = true` |
| MPI2 — Domini siti web | `Website.domain` (fino a 5) |
| MPI3 — Data aggiorn. trasparenza | `Website.transparency_date` |
| MPI4-7 — Date aggiornamento documenti | Da `Document` per tipo |

**Foglio 5 — ELENCO SEDI TERRITORIALI**

| Colonna OAM | Campo DB |
|-------------|----------|
| Numero iscrizione M510 | `Company.oam` |
| Indirizzo | `Branch.address` |
| Numero Civico | `Branch.street_number` |
| Città | `Branch.city` |
| CAP | `Branch.zip_code` |
| Provincia | `Branch.province` |
| Regione | `Branch.region` |
| Responsabile | `Branch.manager_last_name` + `Branch.manager_first_name` |
| Sede Principale (SI/NO) | `Branch.is_main_office` |

---

## 4. Autenticazione e Sicurezza

- Accesso tramite SSO (Google Workspace / Microsoft Entra ID) via `filament-socialite`
- Restrizione per dominio email autorizzato
- Password account email cifrate nel DB (Laravel `encrypted` cast)
- Descrizione SOS cifrata nel DB
- Nessuna registrazione self-service

---

## 5. Regole di Business Principali

| Regola | Descrizione |
|--------|-------------|
| RB-01 | Il `company_id` è sempre il primo record di `companies` (single-tenant) |
| RB-02 | L'importazione OAM esclude pratiche con `rejected_at` valorizzata |
| RB-03 | L'importazione OAM esclude `tipo_prodotto` = 'Utenza' e 'Polizza' |
| RB-04 | Pratiche senza `erogated_at` = in lavorazione; con `erogated_at` nel periodo = intermediate |
| RB-05 | Storni = provvigioni Istituto con "storno" in descrizione → `num_rivalse` e `importo_retrocesse` |
| RB-06 | Un `AuditFinding` è scaduto se status ≠ Resolved/Closed/AcceptedRisk E deadline passata |
| RB-07 | Un `ComplaintRegistry` è scaduto se status ≠ Accepted/Rejected E `deadline_at` passata |
| RB-08 | `ClientiOam.dal` default = 01/01/anno corrente se non specificato |
| RB-09 | Il pivot `clienti_oam` filtra sempre `is_dummy = false` su `Clienti` |
| RB-10 | L'export OAM filtra `OamSemestrale` per `period` corrispondente al semestre (formato YYYYMM) |

---

## 6. Piano di Implementazione

| Task | Descrizione | Priorità |
|------|-------------|---------|
| T-0 | Aggiungere campi indirizzo a `branches` (address, street_number, city, zip_code, province, region) | Alta |
| T-1 | Correggere bug bloccanti (FindingsRelationManager, AuditItem→AuditFinding, getClienteTipo, OamSemestraleService) | Alta |
| T-2 | CompanyResolver per `company_id` dinamico | Alta |
| T-3 | Filament Resource CompanyInspection | Media |
| T-4 | Completare risorse Filament mancanti + RelationManagers | Media |
| T-5 | Action Filament Import Pratiche OAM (semestre/anno) | Alta |
| T-6 | Export Excel OAM multi-foglio (5 fogli) | Alta |

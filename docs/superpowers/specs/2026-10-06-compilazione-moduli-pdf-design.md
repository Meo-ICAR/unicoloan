# Compilazione moduli PDF per pratica — design

Data: 2026-10-06 · Stato: in attesa di revisione

## Obiettivo
Dal dettaglio di una **Pratica**, il mediatore creditizio genera i moduli PDF (in `storage/app/public/module`) già compilati con i dati di pratica e cliente, li scarica e li ritrova archiviati tra i documenti della pratica.

Successo = scelgo una pratica, vedo quali moduli servono e quali dati mancano, ottengo i PDF compilati sull'originale ufficiale (stesso layout, ancora ritoccabile a mano).

## Contesto verificato
- 13 PDF, tutti AcroForm (campi reali via `pdftk dump_data_fields`): da 9 (Compenso) a 162 campi (Fascicolo Mutui). Tipi `Text` e `Button` (checkbox/radio).
- **I nomi dei campi sono per lo più generici** (`Text1`, `dummyFieldName14`, `1a`, `Button4`): il nome non dice cosa contiene. La mappatura campo→dato la decide una persona guardando il modulo. Alcuni sono comprensibili (`Codice Fiscale`, `residenza`, `cliente`, `Banca`).
- Molti `Button` sono risposte di questionario (es. QAV 1a…5c) o scelte dell'operatore: non vanno compilati in automatico. Gli stati hanno valori diversi (`si`, `No`, `Off`).
- `pratiches` (DB `mysql_proforma`) ha: nome/cognome/codice fiscale cliente, banca/ABI, `tipo_prodotto`, `amount`, `rata`, `nrate`, date di stato. Nessun `client_id`: il legame è `pratiches.codice_fiscale = clients.tax_code`.
- `pratiches.tipo_prodotto` è una stringa uguale a `tipoprodotto.name` (Cessione, Prestito, Delega, Mutuo, Aziendale, TFS…).
- `Client` (`proforma.clients`) ha anagrafica, `salary`, e relazioni a `Branch` (indirizzi, morph `branchable`, flag `is_main_office`) e `Document` (morph `documentable`). `Pratica` ha già `documents()` morph.
- Bug esistente: `Client::clientPratiches()` usa `Pratica::class` senza import e risolve a `App\Models\Pratica`, che non esiste. Va corretto con `App\Models\PROFORMA\Pratica`.

## Decisioni
- Si compilano i **campi AcroForm esistenti** con `pdftk fill_form` (via `mikehaertl/php-pdftk`). Niente sovrastampa a coordinate né rigenerazione HTML.
- Nessuna nuova anagrafica: si usa quella esistente. Si aggiungono solo `iban` e `employer_id` a `clients`.
- IBAN in chiaro, escluso dall'activity log.
- Tutti i **nuovi** model/tabelle stanno sulla connessione corrente (`mysql`), senza FK verso `proforma.*`. Le colonne su `clients` vanno nel DB `mysql_proforma`, dove vive il model.

## Dati
### Migration su `proforma.clients` (connessione `mysql_proforma`)
- `iban` string(34) nullable.
- `employer_id` unsignedBigInteger nullable, FK self-reference su `clients.id`, `nullOnDelete`.
- `Client`: aggiunti a `$fillable`; relazioni `employer(): BelongsTo` ed `employees(): HasMany`; correzione di `clientPratiches()`.
- Form Filament cliente: campi `iban` e `employer_id` (Select sui clienti società).

### Nuove tabelle (connessione `mysql`)
`pdf_modules`
- `id`, `name`, `file_path` (relativo a `storage/app/public`), `version` string nullable,
- `tipi_prodotto` json nullable (nomi come in `pratiches.tipo_prodotto`; vuoto = qualunque),
- `client_scope` enum `persona_fisica|persona_giuridica|entrambi`,
- `is_active` bool, timestamps.

`pdf_module_fields`
- `id`, `pdf_module_id` (FK, cascade), `pdf_field_name`, `pdf_field_type` (`text|checkbox`),
- `source_key` string nullable (chiave della whitelist; null = campo non compilato),
- `formatter` string nullable (`date_it`, `money_it`, `upper`, …),
- `checkbox_on_value` string nullable (es. `si`, `Yes`), `checkbox_when` string nullable (condizione semplice, es. `client.is_person`),
- unique (`pdf_module_id`, `pdf_field_name`).

Model `PdfModule` e `PdfModuleField` con factory e seeder, `$connection = 'mysql'`.

### Whitelist delle chiavi sorgente
Enum PHP `ModuleSourceKey` (backed string, con label italiana), unica fonte di verità per la UI di mappatura e per il resolver:

- `pratica.*`: codice_pratica, amount, rata, nrate, denominazione_banca, abi, denominazione_prodotto, data_inserimento_pratica, oggi.
- `client.*`: name, first_name, tax_code, vat_number, email, phone, salary, iban, is_person.
- `employer.*`: name, vat_number, address (sede principale del datore).
- `branch.*` (sede principale del cliente): address, street_number, city, zip_code, province, indirizzo_completo.
- `document.identity.*` (ultimo documento d'identità non scaduto): docnumber, emitted_by, emitted_at, expires_at.

Aggiungere una chiave = una riga nell'enum + il suo ramo nel resolver.

## Componenti
- **`ModuleDataResolver`** (`app/Services/`): `resolve(Pratica): ResolvedModuleData` con array piatto `chiave => valore` e l'elenco delle chiavi mancanti. Trova il cliente via codice fiscale; sede = `branches()` con `is_main_office` (se più di una, la più recente); documento = `documents()` di tipo identità, non scaduto, il più recente.
- **`PdfFormFiller`** (`app/Services/`): dato un `PdfModule` e i dati risolti, applica formattatori e checkbox, chiama `pdftk fill_form` e restituisce il PDF come stringa/percorso temporaneo. Opzione `flatten` (default false, per lasciare il modulo ritoccabile). Il binario si configura con `PDFTK_BINARY` in `config/services.php` (default `pdftk`).
- **`ModuleSuggester`**: dati `tipo_prodotto` della pratica e tipo cliente (`is_person`), restituisce i `PdfModule` attivi pertinenti.
- **`php artisan modules:sync-fields`**: per ogni PDF in `storage/app/public/module` crea/aggiorna il `PdfModule` e le righe `pdf_module_fields` leggendo `pdftk dump_data_fields_utf8`. Non tocca `source_key` già impostati. Segnala i campi spariti rispetto alla versione precedente. Opzione `--diagnostic`: scrive per ogni modulo un PDF in cui ogni campo contiene il proprio nome (e le checkbox sono spuntate), così chi mappa vede **dove** sta ciascun campo sul modulo.
- **Risorsa Filament `PdfModuleResource`**: elenco moduli, scheda con Repeater/tabella dei campi (nome campo, tipo, `Select` della chiave sorgente, formattatore, valore checkbox), pulsante "Scarica PDF diagnostico".
- **Azione "Genera moduli" su `EditPratica`**: modale con i moduli suggeriti (selezionabili, altri attivabili a mano) e anteprima dei dati mancanti per modulo. Alla conferma: genera i PDF, li allega come `Document` della pratica (collezione `documents`, `document_url` valorizzato) e li offre in download (singolo o ZIP). Evento scritto nell'activity log (pratica, moduli, utente), senza valori sensibili.
- **Autorizzazione**: l'azione usa il gate esistente (`checkPiano` + permessi employee, vedi memoria `rbac-employee-permission-gate`); `PdfModuleResource` solo per ruoli amministrativi.

## Flusso
1. Operatore apre la pratica → "Genera moduli".
2. `ModuleSuggester` propone i moduli; `ModuleDataResolver` calcola i dati e i mancanti.
3. Modale: elenco moduli + avviso dati mancanti (cliente non trovato, nessuna sede, nessun documento valido, IBAN vuoto…).
4. Conferma → `PdfFormFiller` per ogni modulo → salvataggio `Document` → download.

## Gestione errori
- Cliente non trovato per codice fiscale: azione bloccata con messaggio chiaro.
- Dato mancante: il campo resta vuoto, non è un errore; compare nell'anteprima.
- `pdftk` assente o in errore: eccezione dedicata, notifica "compilazione non disponibile", log dell'errore con stderr di pdftk; nessun `Document` parziale.
- Modulo il cui PDF è stato sostituito con campi diversi: i campi senza riscontro vengono ignorati e segnalati dal comando `sync-fields`.

## Test (PHPUnit)
- `ModuleDataResolverTest`: cliente persona/società, più sedi, documento scaduto vs valido, datore di lavoro, cliente mancante.
- `PdfFormFillerTest`: compila un PDF di fixture piccolo (es. "Compenso di mediazione") e rilegge i valori con `dump_data_fields`; checkbox on/off; formattatori; `pdftk` mancante (binario finto) → eccezione.
- `SyncModuleFieldsCommandTest`: creazione righe, idempotenza, conservazione di `source_key`.
- `ModuleSuggesterTest`: prodotto × tipo cliente.
- Test Filament: azione "Genera moduli" (salva `Document`, activity log, blocco senza cliente), `PdfModuleResource`.
- Migration `clients`: colonne e FK self.
- Test del fix `clientPratiches()`.
- I test che richiedono `pdftk` si saltano (`markTestSkipped`) se il binario non c'è.

## Fuori ambito (YAGNI)
Firma digitale, invio email dei moduli, compilazione automatica delle risposte ai questionari (QAV), editor visuale a coordinate, OCR, versionamento storico dei moduli generati oltre al `Document`.

## Deploy
Vedi [DEPLOY.md](../../../DEPLOY.md): `pdftk-java` su server, pacchetto Composer, migration (prima `mysql_proforma` poi `mysql`), `php artisan modules:sync-fields`, mappatura campi da Filament.

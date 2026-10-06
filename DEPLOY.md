# Note di deploy: compilazione moduli PDF

Registro dei passi da riportare sul server di produzione. Si aggiorna a ogni modifica che richiede interventi fuori dal codice.

## 1. Dipendenze di sistema (da eseguire sul server, con sudo)

`pdftk` e' il binario che compila i campi AcroForm dei PDF in `storage/app/public/module`. Il pacchetto Composer `mikehaertl/php-pdftk` e' solo un wrapper e non funziona senza il binario.

```bash
sudo apt update
sudo apt install -y pdftk-java   # Ubuntu 24.04: v3.3.3, installa anche default-jre-headless
pdftk --version                  # verifica
```

Se il binario non e' nel PATH di PHP-FPM, impostare il percorso in `config/services.php` / `.env` (vedi sotto, voce `PDFTK_BINARY`, da aggiungere quando il codice lo usa).

Stato:
- [x] sviluppo (WSL, Ubuntu 24.04): installato `pdftk-java` 3.3.3 (verificato 2026-10-06)
- [ ] produzione

## 2. Dipendenze Composer

Aggiunte il 2026-10-06 (`composer require mikehaertl/php-pdftk`):

| Pacchetto | Versione | Ruolo |
|---|---|---|
| mikehaertl/php-pdftk | ^0.14.4 | wrapper PHP di pdftk |
| mikehaertl/php-shellcommand | (transitiva) | esecuzione comandi |
| mikehaertl/php-tmpfile | (transitiva) | file temporanei |

In produzione: `composer install --no-dev --optimize-autoloader` (il `composer.lock` e' gia' aggiornato).

## 3. Migration

Ordine (tutte idempotenti, `php artisan migrate --force`):

| Migration | Connessione | Cosa fa |
|---|---|---|
| `2026_10_06_100000_add_iban_and_employer_to_clients_table` | `mysql_proforma` | aggiunge `iban` ed `employer_id` (self-FK) a `proforma.clients` |
| `2026_10_06_100100_create_pdf_modules_table` | `mysql` | tabella `pdf_modules` |
| `2026_10_06_100200_create_pdf_module_fields_table` | `mysql` | tabella `pdf_module_fields` |

Attenzione: la prima tocca il database `proforma`: eseguirla con l'utente DB che ha `ALTER` su `proforma.clients`.

## 4. File PDF

I PDF stanno in `storage/app/public/module/` (ignorato da git): copiare la cartella sul server e verificare `php artisan storage:link`.

## 5. Variabili d'ambiente

- `PDFTK_BINARY` (opzionale, default `pdftk`): percorso del binario se non e' nel PATH di PHP-FPM.

## 6. Dopo il deploy (una tantum)

```bash
php artisan db:seed --class=PdfModuleSeeder --force   # registra i 13 moduli con prodotti/ambito di default
php artisan modules:sync-fields --diagnostic          # legge i campi dei PDF e genera i PDF diagnostici
php artisan db:seed --class=PdfModuleFieldMappingSeeder --force   # mappatura iniziale dei campi (idempotente, non sovrascrive)
php artisan permissions:sync-resources                # registra la risorsa "Moduli PDF"
```

Poi, da Filament > Sistema > Moduli PDF, mappare i campi di ciascun modulo (aprire il PDF diagnostico per vedere dove sta ogni campo). `modules:sync-fields` va rieseguito quando si sostituisce un PDF con una nuova versione: non tocca le mappature esistenti.

# Compilazione moduli PDF per pratica — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dal dettaglio di una Pratica, generare i moduli PDF AcroForm (in `storage/app/public/module`) compilati con dati di pratica e cliente, archiviarli come `Document` della pratica e renderli scaricabili.

**Architecture:** `pdftk fill_form` (via `mikehaertl/php-pdftk`) compila i campi AcroForm dei PDF originali. Una whitelist (`ModuleSourceKey`) definisce i dati disponibili; le tabelle `pdf_modules`/`pdf_module_fields` (connessione `mysql`) mappano ogni campo PDF a una chiave. Un resolver costruisce i dati da `Pratica` + `Client` (+ sede, documento, datore di lavoro), un filler li scrive nel PDF, un generatore salva i `Document` e logga l'attività; l'azione Filament "Genera moduli" su `EditPratica` orchestra il tutto.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5, Livewire 4, PHPUnit 12, spatie/laravel-medialibrary, spatie/laravel-activitylog v5, pdftk-java 3.3.3, mikehaertl/php-pdftk ^0.14.4.

**Spec:** [docs/superpowers/specs/2026-10-06-compilazione-moduli-pdf-design.md](../specs/2026-10-06-compilazione-moduli-pdf-design.md)

## Global Constraints

- Approccio A: compilare i **campi AcroForm esistenti** con `pdftk fill_form`. Niente sovrastampa a coordinate, niente rigenerazione HTML.
- Nessuna nuova anagrafica: si aggiungono **solo** `iban` ed `employer_id` a `clients`. Indirizzo da `Branch`, documento d'identità da `Document`, stipendio da `clients.salary`.
- IBAN **in chiaro** (nessun cast `encrypted`), mai scritto nell'activity log.
- **Tutti i nuovi model/tabelle** (`PdfModule`, `PdfModuleField`) sulla connessione corrente `mysql`, con `protected $connection = 'mysql';`, **senza FK verso `proforma.*`**. Le colonne `iban`/`employer_id` vanno su `proforma.clients` (connessione `mysql_proforma`, dove vive `Client`) con `Schema::connection('mysql_proforma')`.
- Il legame Pratica→Client è `pratiches.codice_fiscale = clients.tax_code` (o `clients.vat_number`). Non si aggiunge `client_id`.
- Il binario si configura con `PDFTK_BINARY` in `config/services.php` (default `pdftk`).
- Convenzioni di progetto ([CLAUDE.md](../../../CLAUDE.md)): PHPUnit (no Pest), `php artisan make:*` con `--no-interaction`, parentesi graffe sempre, tipi di ritorno espliciti, constructor promotion, PHPDoc invece di commenti inline, commenti/etichette UI in italiano, `vendor/bin/pint --dirty --format agent` prima di chiudere ogni task, factory + seeder per ogni nuovo model, nessuna nuova cartella base.
- Test: `php artisan test --compact --filter=...` (minimo indispensabile). I test che richiedono `pdftk` si saltano con `markTestSkipped` se il binario manca. I test non devono lasciare dati nel DB `proforma` reale: usare `DatabaseTransactions` con `['mysql','mysql_proforma']` o modelli in memoria.
- **Commit**: i passi "Commit" vanno eseguiti **solo se l'utente li ha autorizzati** (regola di sessione: committare solo su richiesta). Messaggi in italiano o inglese coerenti con `git log`; terminare con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Fuori ambito: firma digitale, invio email, compilazione automatica dei questionari QAV, editor a coordinate, OCR, versionamento dei moduli oltre al `Document`.

## Review Focus

Input/condizioni che la spec non nomina ma che colpiscono chi usa il software (ognuno è coperto da un test nel task indicato):

1. Cliente con **una sola Branch non marcata `is_main_office`** (caso comune per persone fisiche): l'indirizzo deve comunque comparire (fallback), non risultare "mancante" — Task 3.
2. Documento d'identità **scaduto** o unico e scaduto: nessun numero/scadenza nel modulo, e le chiavi risultano mancanti nell'anteprima — Task 3.
3. Valori con **accenti, apostrofi e `&`** (es. `Niccolò D'Avéna & Figli`) compilati correttamente nel PDF — Task 4.
4. Pratica senza `codice_pratica` o con `amount` nullo: la generazione non va in errore, il nome file ripiega sull'id — Task 6.
5. Generare **due volte** lo stesso modulo per la stessa pratica: crea due `Document` (storico), non sovrascrive e non fallisce — Task 6.

---

### Task 1: Colonne `iban` e `employer_id` su Client + fix `clientPratiches()`

**Files:**
- Create: `database/migrations/2026_10_06_100000_add_iban_and_employer_to_clients_table.php`
- Modify: `app/Models/Client.php` (fillable, imports, `clientPratiches()`, nuove relazioni)
- Modify: `app/Filament/Resources/Clients/Schemas/ClientForm.php` (nuova sezione nella tab Anagrafica)
- Test: `tests/Feature/ClientEmployerIbanTest.php`

**Interfaces:**
- Produces: `Client::$iban` (string|null), `Client::$employer_id` (int|null), `Client::employer(): BelongsTo`, `Client::employees(): HasMany`, `Client::clientPratiches(): HasMany` funzionante verso `App\Models\PROFORMA\Pratica`.

- [ ] **Step 1: Verifica baseline (pdftk e test DB)**

Run: `pdftk --version | head -1 && php artisan test --compact tests/Feature/BranchAddressTest.php`
Expected: riga `pdftk port to java 3.3.3 ...` e test PASS. Se `pdftk` manca, fermarsi e chiedere all'utente di installarlo (vedi [DEPLOY.md](../../../DEPLOY.md)).

- [ ] **Step 2: Scrivere il test che fallisce**

Creare `tests/Feature/ClientEmployerIbanTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ClientEmployerIbanTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ], $attributes));
    }

    public function test_client_stores_iban_and_links_an_employer(): void
    {
        $employer = $this->makeClient(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]);
        $employee = $this->makeClient([
            'iban' => 'IT60X0542811101000000123456',
            'employer_id' => $employer->id,
        ]);

        $employee->refresh();

        $this->assertSame('IT60X0542811101000000123456', $employee->iban);
        $this->assertTrue($employee->employer->is($employer));
        $this->assertTrue($employer->employees->contains($employee));
    }

    public function test_employer_is_optional_and_nulled_when_the_employer_is_deleted(): void
    {
        $standalone = $this->makeClient();
        $this->assertNull($standalone->employer);

        $employer = $this->makeClient(['name' => 'Acme Spa', 'is_person' => false]);
        $employee = $this->makeClient(['employer_id' => $employer->id]);

        $employer->delete();

        $this->assertNull($employee->fresh()->employer_id);
    }

    public function test_client_pratiches_resolves_through_the_tax_code(): void
    {
        $client = $this->makeClient();
        $pratica = Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-TEST-1',
            'codice_fiscale' => $client->tax_code,
        ]);

        $this->assertTrue($client->clientPratiches->contains('id', $pratica->id));
    }

    public function test_client_form_saves_a_normalized_iban_and_the_employer(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());

        $employer = $this->makeClient(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]);
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm([
                'iban' => 'it60 x054 2811 1010 0000 0123 456',
                'employer_id' => $employer->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();

        $this->assertSame('IT60X0542811101000000123456', $client->iban);
        $this->assertSame($employer->id, $client->employer_id);
    }
}
```

- [ ] **Step 3: Eseguire il test e verificare che fallisca**

Run: `php artisan test --compact tests/Feature/ClientEmployerIbanTest.php`
Expected: FAIL (colonne `iban`/`employer_id` inesistenti, relazione `employer` assente).

- [ ] **Step 4: Creare la migration**

Run: `php artisan make:migration add_iban_and_employer_to_clients_table --no-interaction` e sostituire il contenuto del file creato (rinominarlo in `2026_10_06_100000_add_iban_and_employer_to_clients_table.php` se il timestamp generato è diverso, ma deve precedere le migration del Task 2):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `clients` vive sulla connessione `mysql_proforma` (tabella `proforma.clients`,
     * vedi App\Models\Client). La migration e' idempotente: diventa un no-op dove
     * la tabella non esiste o le colonne sono gia' presenti.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if (! $schema->hasTable('clients')) {
            return;
        }

        if (! $schema->hasColumn('clients', 'iban')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->string('iban', 34)->nullable()->after('phone')->comment('IBAN del cliente (in chiaro)');
            });
        }

        if (! $schema->hasColumn('clients', 'employer_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('employer_id')->nullable()->after('iban')
                    ->comment('Datore di lavoro (self-reference su clients.id)');
                $table->foreign('employer_id')->references('id')->on('clients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_proforma');

        if ($schema->hasColumn('clients', 'employer_id')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->dropForeign(['employer_id']);
                $table->dropColumn('employer_id');
            });
        }

        if ($schema->hasColumn('clients', 'iban')) {
            $schema->table('clients', function (Blueprint $table) {
                $table->dropColumn('iban');
            });
        }
    }
};
```

- [ ] **Step 5: Modificare il model `Client`**

In [app/Models/Client.php](../../../app/Models/Client.php): aggiungere l'import `use App\Models\PROFORMA\Pratica;` (accanto agli altri `use`), aggiungere `'iban'` e `'employer_id'` in coda a `$fillable` (dopo `'is_dummy',`), correggere `clientPratiches()` (ora risolve già `Pratica` grazie all'import, quindi il corpo non cambia) e aggiungere le due relazioni subito dopo `generatedLeads()`:

```php
    /**
     * Il datore di lavoro del cliente (Self-referencing).
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'employer_id');
    }

    /**
     * I clienti che hanno questo cliente come datore di lavoro (Self-referencing).
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Client::class, 'employer_id');
    }
```

- [ ] **Step 6: Aggiungere i campi al form cliente**

In [app/Filament/Resources/Clients/Schemas/ClientForm.php](../../../app/Filament/Resources/Clients/Schemas/ClientForm.php): aggiungere `use Illuminate\Database\Eloquent\Builder;` agli import e, nella tab "Anagrafica", dopo `Section::make('Contatti & Origine')->schema([...])->columns(3),` aggiungere la nuova sezione (stesso livello, prima della chiusura `->schema([` della tab):

```php
                                Section::make('Dati Bancari e Lavorativi')
                                    ->schema([
                                        TextInput::make('iban')
                                            ->label('IBAN')
                                            ->maxLength(34)
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                                ? strtoupper(str_replace(' ', '', $state))
                                                : null),
                                        Select::make('employer_id')
                                            ->label('Datore di lavoro')
                                            ->relationship(
                                                'employer',
                                                'name',
                                                fn (Builder $query) => $query->where('is_person', false),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get) => (bool) $get('is_person')),
                                    ])
                                    ->columns(2),
```

- [ ] **Step 7: Eseguire la migration e i test**

Run: `php artisan migrate --no-interaction && php artisan test --compact tests/Feature/ClientEmployerIbanTest.php`
Expected: migration applicata (anche sul DB `proforma`), 4 test PASS.

Verifica colonne: `php artisan tinker --execute 'echo implode(",", Schema::connection("mysql_proforma")->getColumnListing("clients"));'` deve contenere `iban,employer_id`.

- [ ] **Step 8: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add database/migrations/2026_10_06_100000_add_iban_and_employer_to_clients_table.php app/Models/Client.php app/Filament/Resources/Clients/Schemas/ClientForm.php tests/Feature/ClientEmployerIbanTest.php
git commit -m "feat: IBAN e datore di lavoro su Client, fix relazione clientPratiches"
```

---

### Task 2: Tabelle, model, enum, factory e seeder dei moduli PDF

**Files:**
- Create: `database/migrations/2026_10_06_100100_create_pdf_modules_table.php`
- Create: `database/migrations/2026_10_06_100200_create_pdf_module_fields_table.php`
- Create: `app/Enums/PdfModuleClientScope.php`
- Create: `app/Models/PdfModule.php`, `app/Models/PdfModuleField.php`
- Create: `database/factories/PdfModuleFactory.php`, `database/factories/PdfModuleFieldFactory.php`
- Create: `database/seeders/PdfModuleSeeder.php`
- Test: `tests/Feature/PdfModuleModelTest.php`, `tests/Feature/PdfModuleSeederTest.php`

**Interfaces:**
- Consumes: niente (le enum `ModuleSourceKey`/`ModuleFormatter` vengono create nel Task 3 e referenziate dai cast: **in questo task creare subito anche i due file enum vuoti-ma-validi indicati allo Step 3** per non rompere i cast).
- Produces:
  - `PdfModuleClientScope` (backed string): `PersonaFisica='persona_fisica'`, `PersonaGiuridica='persona_giuridica'`, `Entrambi='entrambi'`; `matches(bool $isPerson): bool`; `getLabel(): string`.
  - `PdfModule`: fillable `name, file_path, version, tipi_prodotto, client_scope, is_active`; `fields(): HasMany`; `scopeActive(Builder): Builder`; `appliesTo(?string $tipoProdotto, bool $isPerson): bool`.
  - `PdfModuleField`: fillable `pdf_module_id, pdf_field_name, pdf_field_type, source_key, formatter, checkbox_on_value, checkbox_when`; `module(): BelongsTo`; `isCheckbox(): bool`; `referencedKeys(): array<int, ModuleSourceKey>`.

- [ ] **Step 1: Scrivere i test che falliscono**

`tests/Feature/PdfModuleModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PdfModuleModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_models_live_on_the_default_mysql_connection(): void
    {
        $this->assertSame('mysql', (new PdfModule)->getConnectionName());
        $this->assertSame('mysql', (new PdfModuleField)->getConnectionName());
    }

    public function test_module_casts_and_active_scope(): void
    {
        PdfModule::factory()->create(['tipi_prodotto' => ['Prestito'], 'client_scope' => 'persona_fisica']);
        PdfModule::factory()->create(['is_active' => false]);

        $module = PdfModule::active()->sole();

        $this->assertSame(['Prestito'], $module->tipi_prodotto);
        $this->assertSame(PdfModuleClientScope::PersonaFisica, $module->client_scope);
    }

    public function test_applies_to_checks_product_case_insensitively_and_client_scope(): void
    {
        $module = new PdfModule([
            'tipi_prodotto' => ['Prestito', 'CHIROGRAFARIO'],
            'client_scope' => PdfModuleClientScope::PersonaFisica,
        ]);

        $this->assertTrue($module->appliesTo('prestito', true));
        $this->assertTrue($module->appliesTo('Chirografario', true));
        $this->assertFalse($module->appliesTo('Mutuo', true));
        $this->assertFalse($module->appliesTo('Prestito', false));
        $this->assertFalse($module->appliesTo(null, true));
    }

    public function test_module_without_products_applies_to_any_product(): void
    {
        $module = new PdfModule(['tipi_prodotto' => null, 'client_scope' => PdfModuleClientScope::Entrambi]);

        $this->assertTrue($module->appliesTo('Qualsiasi', true));
        $this->assertTrue($module->appliesTo(null, false));
    }

    public function test_fields_are_unique_per_module_and_cascade_on_delete(): void
    {
        $module = PdfModule::factory()->create();
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'Text1']);

        try {
            PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'Text1']);
            $this->fail('Il vincolo unique (modulo, nome campo) non ha impedito il duplicato.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $module->delete();

        $this->assertDatabaseCount('pdf_module_fields', 0);
    }

    public function test_checkbox_and_referenced_keys_helpers(): void
    {
        $field = new PdfModuleField([
            'pdf_field_type' => 'checkbox',
            'source_key' => 'client.is_person',
            'checkbox_when' => '!client.is_person',
        ]);

        $this->assertTrue($field->isCheckbox());
        $this->assertSame(
            ['client.is_person', 'client.is_person'],
            array_map(fn ($key) => $key->value, $field->referencedKeys()),
        );
        $this->assertFalse((new PdfModuleField(['pdf_field_type' => 'text']))->isCheckbox());
        $this->assertSame([], (new PdfModuleField(['pdf_field_type' => 'text']))->referencedKeys());
    }
}
```

`tests/Feature/PdfModuleSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PdfModule;
use Database\Seeders\PdfModuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PdfModuleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_registers_the_known_modules_and_is_idempotent(): void
    {
        $this->seed(PdfModuleSeeder::class);
        $this->seed(PdfModuleSeeder::class);

        $this->assertSame(13, PdfModule::count());

        $prestiti = PdfModule::where('name', 'like', '%Prestiti Personali%')->sole();
        $this->assertSame(['Prestito', 'CHIROGRAFARIO', 'Microcredito'], $prestiti->tipi_prodotto);
        $this->assertSame('persona_fisica', $prestiti->client_scope->value);

        $corporate = PdfModule::where('name', 'like', '%Corporate%')->sole();
        $this->assertSame('persona_giuridica', $corporate->client_scope->value);
    }

    public function test_seeder_does_not_overwrite_manual_edits(): void
    {
        $this->seed(PdfModuleSeeder::class);

        $module = PdfModule::where('name', 'like', '%Prestiti Personali%')->sole();
        $module->update(['tipi_prodotto' => ['Cessione'], 'is_active' => false]);

        $this->seed(PdfModuleSeeder::class);

        $module->refresh();
        $this->assertSame(['Cessione'], $module->tipi_prodotto);
        $this->assertFalse($module->is_active);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `php artisan test --compact tests/Feature/PdfModuleModelTest.php tests/Feature/PdfModuleSeederTest.php`
Expected: FAIL (classi/tabelle inesistenti).

- [ ] **Step 3: Creare gli enum (incluso lo scheletro di `ModuleSourceKey` e `ModuleFormatter` usati dai cast)**

`app/Enums/PdfModuleClientScope.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PdfModuleClientScope: string implements HasLabel
{
    case PersonaFisica = 'persona_fisica';
    case PersonaGiuridica = 'persona_giuridica';
    case Entrambi = 'entrambi';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PersonaFisica => 'Persona fisica',
            self::PersonaGiuridica => 'Persona giuridica',
            self::Entrambi => 'Entrambi',
        };
    }

    /**
     * Indica se il modulo e' pertinente per il tipo di cliente.
     */
    public function matches(bool $isPerson): bool
    {
        return match ($this) {
            self::PersonaFisica => $isPerson,
            self::PersonaGiuridica => ! $isPerson,
            self::Entrambi => true,
        };
    }
}
```

`app/Enums/ModuleFormatter.php` (versione completa, nessuna modifica nel Task 3):

```php
<?php

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;
use Throwable;

enum ModuleFormatter: string implements HasLabel
{
    case DateIt = 'date_it';
    case MoneyIt = 'money_it';
    case Upper = 'upper';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DateIt => 'Data (gg/mm/aaaa)',
            self::MoneyIt => 'Importo (1.234,56)',
            self::Upper => 'MAIUSCOLO',
        };
    }

    /**
     * Converte un valore grezzo nel testo da scrivere nel campo PDF.
     * Stringa vuota = nessun dato (il campo non viene toccato).
     */
    public static function format(mixed $value, ?self $formatter): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($formatter) {
            self::DateIt => self::toCarbon($value)?->format('d/m/Y') ?? (string) $value,
            self::MoneyIt => is_numeric($value) ? number_format((float) $value, 2, ',', '.') : (string) $value,
            self::Upper => mb_strtoupper((string) $value),
            null => match (true) {
                $value instanceof CarbonInterface => $value->format('d/m/Y'),
                is_bool($value) => $value ? 'Sì' : 'No',
                default => (string) $value,
            },
        };
    }

    private static function toCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
```

`app/Enums/ModuleSourceKey.php` (versione completa, nessuna modifica nel Task 3):

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whitelist delle chiavi dati utilizzabili per compilare i campi dei moduli PDF.
 * E' l'unica fonte di verita' per la UI di mappatura e per ModuleDataResolver:
 * aggiungere una chiave = una riga qui + il suo ramo nel resolver.
 */
enum ModuleSourceKey: string implements HasLabel
{
    case PraticaCodice = 'pratica.codice_pratica';
    case PraticaImporto = 'pratica.amount';
    case PraticaRata = 'pratica.rata';
    case PraticaNumeroRate = 'pratica.nrate';
    case PraticaBanca = 'pratica.denominazione_banca';
    case PraticaAbi = 'pratica.abi';
    case PraticaProdotto = 'pratica.denominazione_prodotto';
    case PraticaDataInserimento = 'pratica.data_inserimento_pratica';
    case PraticaOggi = 'pratica.oggi';

    case ClienteCognome = 'client.name';
    case ClienteNome = 'client.first_name';
    case ClienteNominativo = 'client.nominativo';
    case ClienteCodiceFiscale = 'client.tax_code';
    case ClientePartitaIva = 'client.vat_number';
    case ClienteEmail = 'client.email';
    case ClienteTelefono = 'client.phone';
    case ClienteStipendio = 'client.salary';
    case ClienteIban = 'client.iban';
    case ClientePersonaFisica = 'client.is_person';

    case DatoreNome = 'employer.name';
    case DatorePartitaIva = 'employer.vat_number';
    case DatoreIndirizzo = 'employer.address';

    case SedeIndirizzo = 'branch.address';
    case SedeCivico = 'branch.street_number';
    case SedeCitta = 'branch.city';
    case SedeCap = 'branch.zip_code';
    case SedeProvincia = 'branch.province';
    case SedeIndirizzoCompleto = 'branch.indirizzo_completo';

    case DocumentoNumero = 'document.identity.docnumber';
    case DocumentoRilasciatoDa = 'document.identity.emitted_by';
    case DocumentoRilasciatoIl = 'document.identity.emitted_at';
    case DocumentoScadenza = 'document.identity.expires_at';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PraticaCodice => 'Pratica: codice',
            self::PraticaImporto => 'Pratica: importo richiesto',
            self::PraticaRata => 'Pratica: rata',
            self::PraticaNumeroRate => 'Pratica: numero rate',
            self::PraticaBanca => 'Pratica: banca',
            self::PraticaAbi => 'Pratica: ABI',
            self::PraticaProdotto => 'Pratica: prodotto',
            self::PraticaDataInserimento => 'Pratica: data inserimento',
            self::PraticaOggi => 'Data odierna',
            self::ClienteCognome => 'Cliente: cognome / ragione sociale',
            self::ClienteNome => 'Cliente: nome',
            self::ClienteNominativo => 'Cliente: cognome e nome',
            self::ClienteCodiceFiscale => 'Cliente: codice fiscale / P.IVA',
            self::ClientePartitaIva => 'Cliente: partita IVA',
            self::ClienteEmail => 'Cliente: email',
            self::ClienteTelefono => 'Cliente: telefono',
            self::ClienteStipendio => 'Cliente: stipendio',
            self::ClienteIban => 'Cliente: IBAN',
            self::ClientePersonaFisica => 'Cliente: è persona fisica (casella)',
            self::DatoreNome => 'Datore di lavoro: ragione sociale',
            self::DatorePartitaIva => 'Datore di lavoro: partita IVA',
            self::DatoreIndirizzo => 'Datore di lavoro: indirizzo sede',
            self::SedeIndirizzo => 'Sede cliente: indirizzo',
            self::SedeCivico => 'Sede cliente: civico',
            self::SedeCitta => 'Sede cliente: città',
            self::SedeCap => 'Sede cliente: CAP',
            self::SedeProvincia => 'Sede cliente: provincia',
            self::SedeIndirizzoCompleto => 'Sede cliente: indirizzo completo',
            self::DocumentoNumero => 'Documento identità: numero',
            self::DocumentoRilasciatoDa => 'Documento identità: rilasciato da',
            self::DocumentoRilasciatoIl => 'Documento identità: data rilascio',
            self::DocumentoScadenza => 'Documento identità: scadenza',
        };
    }
}
```

- [ ] **Step 4: Creare le migration**

`database/migrations/2026_10_06_100100_create_pdf_modules_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdf_modules')) {
            return;
        }

        Schema::create('pdf_modules', function (Blueprint $table) {
            $table->comment('Moduli PDF compilabili (AcroForm) presenti in storage/app/public/module.');

            $table->id();
            $table->string('name')->comment('Nome mostrato all\'operatore');
            $table->string('file_path')->unique()->comment('Percorso relativo al disco public');
            $table->string('version')->nullable()->comment('Versione del modulo (es. 03_2026)');
            $table->json('tipi_prodotto')->nullable()
                ->comment('Nomi tipo_prodotto pertinenti (come in pratiches.tipo_prodotto); null = tutti');
            $table->string('client_scope', 30)->default('entrambi')
                ->comment('persona_fisica | persona_giuridica | entrambi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_modules');
    }
};
```

`database/migrations/2026_10_06_100200_create_pdf_module_fields_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdf_module_fields')) {
            return;
        }

        Schema::create('pdf_module_fields', function (Blueprint $table) {
            $table->comment('Mappatura campo AcroForm -> chiave dati (ModuleSourceKey) per ogni modulo PDF.');

            $table->id();
            $table->foreignId('pdf_module_id')->constrained('pdf_modules')->cascadeOnDelete();
            $table->string('pdf_field_name');
            $table->string('pdf_field_type', 20)->default('text')->comment('text | checkbox');
            $table->string('source_key')->nullable()->comment('Valore di ModuleSourceKey; null = campo non compilato');
            $table->string('formatter', 30)->nullable()->comment('Valore di ModuleFormatter');
            $table->string('checkbox_on_value', 50)->nullable()->comment('Valore dello stato "spuntato" (es. si, Yes)');
            $table->string('checkbox_when')->nullable()->comment('Chiave ModuleSourceKey, con ! iniziale per negare');
            $table->timestamps();

            $table->unique(['pdf_module_id', 'pdf_field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_module_fields');
    }
};
```

- [ ] **Step 5: Creare i model**

`app/Models/PdfModule.php`:

```php
<?php

namespace App\Models;

use App\Enums\PdfModuleClientScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfModule extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'pdf_modules';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'file_path',
        'version',
        'tipi_prodotto',
        'client_scope',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tipi_prodotto' => 'array',
        'client_scope' => PdfModuleClientScope::class,
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(PdfModuleField::class, 'pdf_module_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Il modulo e' pertinente se il prodotto e' tra quelli indicati (o l'elenco e' vuoto)
     * e il tipo di cliente rientra nell'ambito del modulo.
     */
    public function appliesTo(?string $tipoProdotto, bool $isPerson): bool
    {
        $tipi = array_map('mb_strtolower', $this->tipi_prodotto ?? []);

        $productMatches = $tipi === []
            || ($tipoProdotto !== null && in_array(mb_strtolower($tipoProdotto), $tipi, true));

        $scope = $this->client_scope ?? PdfModuleClientScope::Entrambi;

        return $productMatches && $scope->matches($isPerson);
    }
}
```

`app/Models/PdfModuleField.php`:

```php
<?php

namespace App\Models;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfModuleField extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'pdf_module_fields';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'pdf_module_id',
        'pdf_field_name',
        'pdf_field_type',
        'source_key',
        'formatter',
        'checkbox_on_value',
        'checkbox_when',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_key' => ModuleSourceKey::class,
        'formatter' => ModuleFormatter::class,
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(PdfModule::class, 'pdf_module_id');
    }

    public function isCheckbox(): bool
    {
        return $this->pdf_field_type === 'checkbox';
    }

    /**
     * Le chiavi dati da cui dipende la compilazione del campo.
     *
     * @return array<int, ModuleSourceKey>
     */
    public function referencedKeys(): array
    {
        $keys = [];

        if ($this->source_key instanceof ModuleSourceKey) {
            $keys[] = $this->source_key;
        }

        if (filled($this->checkbox_when)) {
            $when = ModuleSourceKey::tryFrom(ltrim($this->checkbox_when, '!'));

            if ($when !== null) {
                $keys[] = $when;
            }
        }

        return $keys;
    }
}
```

- [ ] **Step 6: Creare factory e seeder**

`database/factories/PdfModuleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PdfModule>
 */
class PdfModuleFactory extends Factory
{
    protected $model = PdfModule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'file_path' => 'module/'.Str::slug($name).'-'.fake()->unique()->numerify('####').'.pdf',
            'version' => null,
            'tipi_prodotto' => null,
            'client_scope' => PdfModuleClientScope::Entrambi,
            'is_active' => true,
        ];
    }
}
```

`database/factories/PdfModuleFieldFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdfModuleField>
 */
class PdfModuleFieldFactory extends Factory
{
    protected $model = PdfModuleField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pdf_module_id' => PdfModule::factory(),
            'pdf_field_name' => 'Text'.fake()->unique()->numerify('####'),
            'pdf_field_type' => 'text',
            'source_key' => null,
            'formatter' => null,
            'checkbox_on_value' => null,
            'checkbox_when' => null,
        ];
    }

    public function checkbox(string $onValue = 'si'): static
    {
        return $this->state(fn () => [
            'pdf_field_type' => 'checkbox',
            'checkbox_on_value' => $onValue,
        ]);
    }
}
```

`database/seeders/PdfModuleSeeder.php` (i nomi file sono quelli esatti presenti in `storage/app/public/module`; `firstOrCreate` per non sovrascrivere modifiche manuali; il nome modulo deriva dal file come farà `PdfFieldSynchronizer::moduleNameFromPath()` nel Task 5 — qui si duplica la normalizzazione in modo semplice per non creare dipendenze incrociate: il nome viene poi allineato dal test del Task 5):

```php
<?php

namespace Database\Seeders;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use Illuminate\Database\Seeder;

class PdfModuleSeeder extends Seeder
{
    /**
     * Moduli noti: file => [tipi_prodotto|null, ambito cliente].
     *
     * @var array<string, array{0: array<int, string>|null, 1: PdfModuleClientScope}>
     */
    private const MODULES = [
        '20240403 - proforma fattura mutuo NO IVA.pdf' => [['Mutuo', 'IPOTECARIO'], PdfModuleClientScope::Entrambi],
        'Compenso di mediazione fuori convenzione.pdf' => [null, PdfModuleClientScope::Entrambi],
        'Delega richiesta allegati statali_compressed.pdf' => [null, PdfModuleClientScope::Entrambi],
        'NUOVA  Infomativa privacy 2025 - Editato_compressed.pdf' => [null, PdfModuleClientScope::Entrambi],
        'QAV Persona fisica_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
        'QAV Persona giuridica_compressed.pdf' => [null, PdfModuleClientScope::PersonaGiuridica],
        'Races VERS. 03_2026 - Fascicolo completo retail  MUTUI compilabile_compressed.pdf' => [['Mutuo', 'IPOTECARIO'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail  Prestiti Personali compilabile_compressed.pdf' => [['Prestito', 'CHIROGRAFARIO', 'Microcredito'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail  TFS compilabile_compressed.pdf' => [['TFS'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile_compressed.pdf' => [['Cessione', 'Delega'], PdfModuleClientScope::PersonaFisica],
        'Races VERS. 06_2025 - Fascicolo completo Corporate compilabile_compressed.pdf' => [['Aziendale', 'PRESTITO AZIENDALE'], PdfModuleClientScope::PersonaGiuridica],
        'Richiesta Conteggio Estintivo_compressed.pdf' => [null, PdfModuleClientScope::Entrambi],
        'patronato_compressed.pdf' => [null, PdfModuleClientScope::PersonaFisica],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $file => [$tipi, $scope]) {
            PdfModule::firstOrCreate(
                ['file_path' => 'module/'.$file],
                [
                    'name' => self::nameFromFile($file),
                    'tipi_prodotto' => $tipi,
                    'client_scope' => $scope,
                    'is_active' => true,
                ],
            );
        }
    }

    private static function nameFromFile(string $file): string
    {
        $name = str_replace('_compressed', '', pathinfo($file, PATHINFO_FILENAME));

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
```

- [ ] **Step 7: Eseguire i test**

Run: `php artisan test --compact tests/Feature/PdfModuleModelTest.php tests/Feature/PdfModuleSeederTest.php`
Expected: tutti PASS.

- [ ] **Step 8: Applicare le migration sul DB di sviluppo, Pint, commit**

Run: `php artisan migrate --no-interaction && vendor/bin/pint --dirty --format agent`

```bash
git add app/Enums app/Models/PdfModule.php app/Models/PdfModuleField.php database tests/Feature/PdfModuleModelTest.php tests/Feature/PdfModuleSeederTest.php
git commit -m "feat: tabelle, model, enum e seeder dei moduli PDF"
```

---

### Task 3: `ModuleDataResolver` e `ResolvedModuleData`

**Files:**
- Create: `app/Services/ResolvedModuleData.php`
- Create: `app/Services/ModuleDataResolver.php`
- Test: `tests/Unit/ModuleDataResolverTest.php` (in memoria, nessun DB)
- Test: `tests/Feature/ModuleDataResolverClientLookupTest.php` (DB, transazioni)

**Interfaces:**
- Consumes: `ModuleSourceKey` (Task 2), `Client::employer()`, `Client::branches()`, `Client::documents()`.
- Produces:
  - `ResolvedModuleData::__construct(array $values)`; `get(ModuleSourceKey $key): mixed`; `has(ModuleSourceKey $key): bool` (`null` e `''` = assente; `false` e `0` = presenti); `isTruthy(ModuleSourceKey $key): bool`; `missingAmong(array $keys): array<int, ModuleSourceKey>`.
  - `ModuleDataResolver::findClient(Pratica $pratica): ?Client`; `ModuleDataResolver::resolve(Pratica $pratica, Client $client): ResolvedModuleData`; costanti `IDENTITY_TYPE_CODES = ['CARTA_IDENTITA','DOCUMENTO_IDENTIFICATIVO']`, `IDENTITY_TYPE_NAMES = ['Patente di Guida']`.

- [ ] **Step 1: Scrivere il test unitario (in memoria)**

`tests/Unit/ModuleDataResolverTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use Carbon\Carbon;
use Tests\TestCase;

class ModuleDataResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-06 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pratica(array $attributes = []): Pratica
    {
        return new Pratica(array_merge([
            'id' => 'pratica-1',
            'codice_pratica' => 'P-2026-001',
            'codice_fiscale' => 'RSSMRA80A01H501U',
            'tipo_prodotto' => 'Prestito',
            'denominazione_banca' => 'Banca Test',
            'abi' => '03069',
            'denominazione_prodotto' => 'Prestito Personale',
            'data_inserimento_pratica' => '2026-09-01',
            'amount' => 15000,
            'rata' => 250.5,
            'nrate' => 72,
        ], $attributes));
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<int, Document>  $documents
     */
    private function client(array $attributes = [], array $branches = [], array $documents = [], ?Client $employer = null): Client
    {
        $client = new Client(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'tax_code' => 'RSSMRA80A01H501U',
            'vat_number' => null,
            'email' => 'mario@example.test',
            'phone' => '3331234567',
            'is_person' => true,
            'salary' => 1800,
            'iban' => 'IT60X0542811101000000123456',
        ], $attributes));

        $client->setRelation('branches', collect($branches));
        $client->setRelation('documents', collect($documents));
        $client->setRelation('employer', $employer);

        return $client;
    }

    private function branch(array $attributes = [], ?string $createdAt = null): Branch
    {
        $branch = (new Branch)->forceFill(array_merge([
            'address' => 'Via Roma',
            'street_number' => '10',
            'city' => 'Napoli',
            'zip_code' => '80100',
            'province' => 'NA',
            'is_main_office' => true,
            'dismissed_at' => null,
        ], $attributes));

        $branch->created_at = Carbon::parse($createdAt ?? '2026-01-01');

        return $branch;
    }

    private function identity(array $attributes = [], string $code = 'CARTA_IDENTITA', string $typeName = "Carta d'Identità"): Document
    {
        $document = (new Document)->forceFill(array_merge([
            'docnumber' => 'AY1234567',
            'emitted_by' => 'Comune di Napoli',
            'emitted_at' => '2022-05-01',
            'expires_at' => '2032-05-01',
        ], $attributes));

        $document->setRelation('documentType', (new DocumentType)->forceFill(['code' => $code, 'name' => $typeName]));

        return $document;
    }

    private function resolve(Pratica $pratica, Client $client)
    {
        return (new ModuleDataResolver)->resolve($pratica, $client);
    }

    public function test_resolves_pratica_and_client_values(): void
    {
        $data = $this->resolve($this->pratica(), $this->client());

        $this->assertSame('P-2026-001', $data->get(Key::PraticaCodice));
        $this->assertEquals(15000, $data->get(Key::PraticaImporto));
        $this->assertSame(72, $data->get(Key::PraticaNumeroRate));
        $this->assertSame('Banca Test', $data->get(Key::PraticaBanca));
        $this->assertSame('03069', $data->get(Key::PraticaAbi));
        $this->assertSame('2026-10-06', $data->get(Key::PraticaOggi)->toDateString());
        $this->assertSame('2026-09-01', $data->get(Key::PraticaDataInserimento)->toDateString());

        $this->assertSame('Rossi', $data->get(Key::ClienteCognome));
        $this->assertSame('Mario', $data->get(Key::ClienteNome));
        $this->assertSame('Rossi Mario', $data->get(Key::ClienteNominativo));
        $this->assertSame('RSSMRA80A01H501U', $data->get(Key::ClienteCodiceFiscale));
        $this->assertSame('IT60X0542811101000000123456', $data->get(Key::ClienteIban));
        $this->assertTrue($data->get(Key::ClientePersonaFisica));
    }

    public function test_company_nominativo_is_just_the_business_name(): void
    {
        $data = $this->resolve($this->pratica(), $this->client(['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false]));

        $this->assertSame('Acme Spa', $data->get(Key::ClienteNominativo));
        $this->assertFalse($data->get(Key::ClientePersonaFisica));
    }

    public function test_main_branch_is_the_most_recent_flagged_one_and_builds_the_full_address(): void
    {
        $old = $this->branch(['address' => 'Via Vecchia', 'city' => 'Roma', 'zip_code' => '00100', 'province' => 'RM'], '2020-01-01');
        $recent = $this->branch([], '2026-03-01');

        $data = $this->resolve($this->pratica(), $this->client(branches: [$old, $recent]));

        $this->assertSame('Via Roma', $data->get(Key::SedeIndirizzo));
        $this->assertSame('10', $data->get(Key::SedeCivico));
        $this->assertSame('Via Roma 10, 80100 Napoli (NA)', $data->get(Key::SedeIndirizzoCompleto));
    }

    public function test_a_single_branch_not_flagged_as_main_office_is_used_as_fallback(): void
    {
        $branch = $this->branch(['is_main_office' => false]);

        $data = $this->resolve($this->pratica(), $this->client(branches: [$branch]));

        $this->assertSame('Via Roma 10, 80100 Napoli (NA)', $data->get(Key::SedeIndirizzoCompleto));
    }

    public function test_dismissed_branches_are_ignored(): void
    {
        $dismissed = $this->branch(['dismissed_at' => '2025-01-01']);

        $data = $this->resolve($this->pratica(), $this->client(branches: [$dismissed]));

        $this->assertFalse($data->has(Key::SedeIndirizzo));
        $this->assertContains(Key::SedeIndirizzoCompleto, $data->missingAmong([Key::SedeIndirizzoCompleto]));
    }

    public function test_identity_document_prefers_a_valid_recent_one_and_skips_expired_and_other_types(): void
    {
        $expired = $this->identity(['docnumber' => 'SCADUTO', 'expires_at' => '2025-01-01', 'emitted_at' => '2015-01-01']);
        $valid = $this->identity(['docnumber' => 'VALIDO', 'emitted_at' => '2023-01-01', 'expires_at' => '2033-01-01']);
        $other = $this->identity(['docnumber' => 'ALTRO'], 'BUSTA_PAGA', 'Busta paga');

        $data = $this->resolve($this->pratica(), $this->client(documents: [$expired, $other, $valid]));

        $this->assertSame('VALIDO', $data->get(Key::DocumentoNumero));
        $this->assertSame('Comune di Napoli', $data->get(Key::DocumentoRilasciatoDa));
        $this->assertSame('2023-01-01', $data->get(Key::DocumentoRilasciatoIl)->toDateString());
        $this->assertSame('2033-01-01', $data->get(Key::DocumentoScadenza)->toDateString());
    }

    public function test_driving_licence_is_accepted_by_type_name(): void
    {
        $licence = $this->identity(['docnumber' => 'PAT-1'], 'PATENTE', 'Patente di Guida');

        $data = $this->resolve($this->pratica(), $this->client(documents: [$licence]));

        $this->assertSame('PAT-1', $data->get(Key::DocumentoNumero));
    }

    public function test_only_expired_identity_document_yields_no_values_and_is_reported_missing(): void
    {
        $expired = $this->identity(['expires_at' => '2025-01-01']);

        $data = $this->resolve($this->pratica(), $this->client(documents: [$expired]));

        $this->assertFalse($data->has(Key::DocumentoNumero));
        $this->assertSame(
            [Key::DocumentoNumero, Key::DocumentoScadenza],
            $data->missingAmong([Key::DocumentoNumero, Key::ClienteNome, Key::DocumentoScadenza]),
        );
    }

    public function test_employer_values_come_from_the_employer_and_its_main_branch(): void
    {
        $employer = $this->client(
            ['name' => 'Acme Spa', 'first_name' => null, 'is_person' => false, 'vat_number' => '01234567890'],
            [$this->branch(['address' => 'Corso Italia', 'street_number' => '5', 'city' => 'Milano', 'zip_code' => '20100', 'province' => 'MI'])],
        );

        $data = $this->resolve($this->pratica(), $this->client(employer: $employer));

        $this->assertSame('Acme Spa', $data->get(Key::DatoreNome));
        $this->assertSame('01234567890', $data->get(Key::DatorePartitaIva));
        $this->assertSame('Corso Italia 5, 20100 Milano (MI)', $data->get(Key::DatoreIndirizzo));
    }

    public function test_missing_values_are_reported_but_false_and_zero_count_as_present(): void
    {
        $client = $this->client(['is_person' => false, 'salary' => 0, 'iban' => null, 'email' => '']);

        $data = $this->resolve($this->pratica(['rata' => null]), $client);

        $this->assertTrue($data->has(Key::ClientePersonaFisica));
        $this->assertFalse($data->isTruthy(Key::ClientePersonaFisica));
        $this->assertTrue($data->has(Key::ClienteStipendio));
        $this->assertSame(
            [Key::ClienteIban, Key::ClienteEmail, Key::PraticaRata, Key::DatoreNome],
            $data->missingAmong([Key::ClienteIban, Key::ClienteEmail, Key::PraticaRata, Key::DatoreNome, Key::ClienteNome]),
        );
    }
}
```

`tests/Feature/ModuleDataResolverClientLookupTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleDataResolverClientLookupTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    public function test_finds_the_client_by_tax_code(): void
    {
        $code = strtoupper(Str::random(16));
        $client = Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);

        $found = (new ModuleDataResolver)->findClient(new Pratica(['codice_fiscale' => $code]));

        $this->assertTrue($found->is($client));
    }

    public function test_finds_a_company_by_vat_number_when_the_pratica_carries_the_vat_number(): void
    {
        $vat = (string) random_int(10000000000, 99999999999);
        $client = Client::create(['name' => 'Acme Spa', 'is_person' => false, 'vat_number' => $vat]);

        $found = (new ModuleDataResolver)->findClient(new Pratica(['codice_fiscale' => $vat]));

        $this->assertTrue($found->is($client));
    }

    public function test_returns_null_for_blank_or_unknown_codes(): void
    {
        $resolver = new ModuleDataResolver;

        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => null])));
        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => ''])));
        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => 'NONESISTE0000000'])));
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `php artisan test --compact tests/Unit/ModuleDataResolverTest.php tests/Feature/ModuleDataResolverClientLookupTest.php`
Expected: FAIL (classi inesistenti).

- [ ] **Step 3: Implementare `ResolvedModuleData`**

`app/Services/ResolvedModuleData.php`:

```php
<?php

namespace App\Services;

use App\Enums\ModuleSourceKey;

/**
 * Valori grezzi risolti per una pratica, indicizzati per valore di ModuleSourceKey.
 * Un valore e' "assente" se e' null o stringa vuota; false e 0 sono valori validi.
 */
final class ResolvedModuleData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public readonly array $values) {}

    public function get(ModuleSourceKey $key): mixed
    {
        return $this->values[$key->value] ?? null;
    }

    public function has(ModuleSourceKey $key): bool
    {
        $value = $this->get($key);

        return $value !== null && $value !== '';
    }

    public function isTruthy(ModuleSourceKey $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * @param  iterable<ModuleSourceKey>  $keys
     * @return array<int, ModuleSourceKey>
     */
    public function missingAmong(iterable $keys): array
    {
        $missing = [];

        foreach ($keys as $key) {
            if (! $this->has($key) && ! in_array($key, $missing, true)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
```

- [ ] **Step 4: Implementare `ModuleDataResolver`**

`app/Services/ModuleDataResolver.php`:

```php
<?php

namespace App\Services;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\PROFORMA\Pratica;

/**
 * Costruisce i dati con cui compilare i moduli PDF a partire da una Pratica e dal suo Client.
 */
class ModuleDataResolver
{
    /**
     * Codici di DocumentType che identificano un documento d'identita'.
     *
     * @var array<int, string>
     */
    public const IDENTITY_TYPE_CODES = ['CARTA_IDENTITA', 'DOCUMENTO_IDENTIFICATIVO'];

    /**
     * Nomi di DocumentType senza codice ma validi come documento d'identita'.
     *
     * @var array<int, string>
     */
    public const IDENTITY_TYPE_NAMES = ['Patente di Guida'];

    /**
     * Il legame pratica -> cliente e' il codice fiscale (o la P.IVA per le societa').
     */
    public function findClient(Pratica $pratica): ?Client
    {
        $code = $pratica->codice_fiscale;

        if (blank($code)) {
            return null;
        }

        return Client::query()
            ->where(fn ($query) => $query->where('tax_code', $code)->orWhere('vat_number', $code))
            ->first();
    }

    public function resolve(Pratica $pratica, Client $client): ResolvedModuleData
    {
        $client->loadMissing(['branches', 'documents.documentType', 'employer.branches']);

        $branch = $this->mainBranch($client);
        $employer = $client->employer;
        $employerBranch = $employer ? $this->mainBranch($employer) : null;
        $identity = $this->identityDocument($client);

        return new ResolvedModuleData([
            Key::PraticaCodice->value => $pratica->codice_pratica,
            Key::PraticaImporto->value => $pratica->amount,
            Key::PraticaRata->value => $pratica->rata,
            Key::PraticaNumeroRate->value => $pratica->nrate,
            Key::PraticaBanca->value => $pratica->denominazione_banca,
            Key::PraticaAbi->value => $pratica->abi,
            Key::PraticaProdotto->value => $pratica->denominazione_prodotto,
            Key::PraticaDataInserimento->value => $pratica->data_inserimento_pratica,
            Key::PraticaOggi->value => now(),

            Key::ClienteCognome->value => $client->name,
            Key::ClienteNome->value => $client->first_name,
            Key::ClienteNominativo->value => $this->nominativo($client),
            Key::ClienteCodiceFiscale->value => $client->tax_code,
            Key::ClientePartitaIva->value => $client->vat_number,
            Key::ClienteEmail->value => $client->email,
            Key::ClienteTelefono->value => $client->phone,
            Key::ClienteStipendio->value => $client->salary,
            Key::ClienteIban->value => $client->iban,
            Key::ClientePersonaFisica->value => (bool) $client->is_person,

            Key::DatoreNome->value => $employer?->name,
            Key::DatorePartitaIva->value => $employer?->vat_number,
            Key::DatoreIndirizzo->value => $employerBranch ? $this->fullAddress($employerBranch) : null,

            Key::SedeIndirizzo->value => $branch?->address,
            Key::SedeCivico->value => $branch?->street_number,
            Key::SedeCitta->value => $branch?->city,
            Key::SedeCap->value => $branch?->zip_code,
            Key::SedeProvincia->value => $branch?->province,
            Key::SedeIndirizzoCompleto->value => $branch ? $this->fullAddress($branch) : null,

            Key::DocumentoNumero->value => $identity?->docnumber,
            Key::DocumentoRilasciatoDa->value => $identity?->emitted_by,
            Key::DocumentoRilasciatoIl->value => $identity?->emitted_at,
            Key::DocumentoScadenza->value => $identity?->expires_at,
        ]);
    }

    private function nominativo(Client $client): string
    {
        return trim(($client->name ?? '').' '.($client->first_name ?? ''));
    }

    /**
     * Sede principale: la piu' recente tra quelle marcate `is_main_office`; se nessuna lo e',
     * la piu' recente tra le sedi non dismesse. Le sedi dismesse non vengono mai usate.
     */
    private function mainBranch(Client $client): ?Branch
    {
        $active = $client->branches->filter(fn (Branch $branch) => $branch->dismissed_at === null);

        $candidates = $active->where('is_main_office', true);

        if ($candidates->isEmpty()) {
            $candidates = $active;
        }

        return $candidates
            ->sortByDesc(fn (Branch $branch) => [$branch->created_at?->getTimestamp() ?? 0, (int) $branch->id])
            ->first();
    }

    private function fullAddress(Branch $branch): ?string
    {
        $street = trim(($branch->address ?? '').' '.($branch->street_number ?? ''));
        $city = trim(($branch->zip_code ?? '').' '.($branch->city ?? ''));
        $province = filled($branch->province) ? '('.$branch->province.')' : '';

        $address = implode(', ', array_filter([$street, trim($city.' '.$province)]));

        return $address === '' ? null : $address;
    }

    /**
     * Ultimo documento d'identita' non scaduto del cliente (per data di rilascio).
     */
    private function identityDocument(Client $client): ?Document
    {
        return $client->documents
            ->filter(function (Document $document): bool {
                $type = $document->documentType;

                if ($type === null) {
                    return false;
                }

                $isIdentity = in_array($type->code, self::IDENTITY_TYPE_CODES, true)
                    || in_array($type->name, self::IDENTITY_TYPE_NAMES, true);

                $notExpired = $document->expires_at === null || $document->expires_at->greaterThanOrEqualTo(today());

                return $isIdentity && $notExpired;
            })
            ->sortByDesc(fn (Document $document) => [$document->emitted_at?->getTimestamp() ?? 0, $document->created_at?->getTimestamp() ?? 0])
            ->first();
    }
}
```

- [ ] **Step 5: Eseguire i test**

Run: `php artisan test --compact tests/Unit/ModuleDataResolverTest.php tests/Feature/ModuleDataResolverClientLookupTest.php`
Expected: tutti PASS. Se `test_main_branch...` o i test sul documento falliscono per l'ordinamento con array come chiave, sostituire la closure di `sortByDesc` con una chiave scalare (`timestamp * 1_000_000 + id`) mantenendo lo stesso criterio.

- [ ] **Step 6: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/ResolvedModuleData.php app/Services/ModuleDataResolver.php tests/Unit/ModuleDataResolverTest.php tests/Feature/ModuleDataResolverClientLookupTest.php
git commit -m "feat: ModuleDataResolver per i dati dei moduli PDF"
```

---

### Task 4: `PdfFormFiller` (pdftk), configurazione ed eccezione

**Files:**
- Create: `app/Services/PdfFormException.php`
- Create: `app/Services/PdfFormFiller.php`
- Modify: `config/services.php` (chiave `pdftk`)
- Create: `tests/Concerns/ReadsPdfFields.php` (helper di test condiviso)
- Test: `tests/Feature/PdfFormFillerTest.php`
- Fixture già presente e validata: `tests/Fixtures/pdf/modulo-prova.pdf` (campi `cliente`, `importo`, `non_mappato` di tipo testo e `consenso` checkbox con stato `si`)

**Interfaces:**
- Consumes: `PdfModule`/`PdfModuleField` (Task 2), `ModuleFormatter`, `ModuleSourceKey`, `ResolvedModuleData` (Task 3).
- Produces:
  - `PdfFormException extends RuntimeException`: `::missingTemplate(string $path)`, `::pdftkFailed(string $details)`.
  - `PdfFormFiller::open(string $absolutePath): Pdf` (istanza `mikehaertl\pdftk\Pdf` col binario configurato).
  - `PdfFormFiller::buildFieldValues(PdfModule $module, ResolvedModuleData $data): array<string, string>`.
  - `PdfFormFiller::fill(PdfModule $module, ResolvedModuleData $data, bool $flatten = false): string` (contenuto del PDF).
  - `PdfFormFiller::fillDiagnostic(PdfModule $module): string` (ogni campo testo contiene il proprio nome; checkbox spuntate).
  - `PdfFormFiller::storeDiagnostic(PdfModule $module): string` (scrive su disco `public` in `module-diagnostica/{slug}.pdf` e ritorna il percorso relativo).

- [ ] **Step 1: Creare l'helper di test**

`tests/Concerns/ReadsPdfFields.php`:

```php
<?php

namespace Tests\Concerns;

use mikehaertl\pdftk\Pdf;

trait ReadsPdfFields
{
    protected function pdftkAvailable(): bool
    {
        return trim((string) shell_exec('command -v pdftk 2>/dev/null')) !== '';
    }

    protected function skipUnlessPdftk(): void
    {
        if (! $this->pdftkAvailable()) {
            $this->markTestSkipped('pdftk non installato.');
        }
    }

    protected function fixturePdfContents(): string
    {
        return file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf'));
    }

    /**
     * Legge i campi di un PDF (contenuto binario) come mappa nome => valore.
     *
     * @return array<string, string|null>
     */
    protected function readPdfFields(string $pdfBytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pdfread');
        file_put_contents($path, $pdfBytes);

        $dataFields = (new Pdf($path, ['command' => 'pdftk']))->getDataFields();
        @unlink($path);

        $fields = [];

        foreach ($dataFields === false ? [] : $dataFields->__toArray() as $field) {
            $fields[$field['FieldName']] = $field['FieldValue'] ?? null;
        }

        return $fields;
    }
}
```

- [ ] **Step 2: Scrivere il test che fallisce**

`tests/Feature/PdfFormFillerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey as Key;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Services\PdfFormException;
use App\Services\PdfFormFiller;
use App\Services\ResolvedModuleData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfFormFillerTest extends TestCase
{
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function module(array $fields, string $path = 'module/modulo-prova.pdf'): PdfModule
    {
        $module = new PdfModule(['name' => 'Modulo prova', 'file_path' => $path]);
        $module->setRelation('fields', collect(array_map(fn (array $attributes) => new PdfModuleField($attributes), $fields)));

        return $module;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function data(array $values): ResolvedModuleData
    {
        return new ResolvedModuleData($values);
    }

    public function test_build_field_values_formats_text_and_resolves_checkboxes(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNominativo, 'formatter' => ModuleFormatter::Upper],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text', 'source_key' => Key::PraticaImporto, 'formatter' => ModuleFormatter::MoneyIt],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica, 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'non_mappato', 'pdf_field_type' => 'text', 'source_key' => null],
        ]);

        $values = (new PdfFormFiller)->buildFieldValues($module, $this->data([
            Key::ClienteNominativo->value => 'Rossi Mario',
            Key::PraticaImporto->value => '15000.00',
            Key::ClientePersonaFisica->value => true,
        ]));

        $this->assertSame(['cliente' => 'ROSSI MARIO', 'importo' => '15.000,00', 'consenso' => 'si'], $values);
    }

    public function test_checkbox_off_when_falsy_and_negated_condition(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'a', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica, 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'b', 'pdf_field_type' => 'checkbox', 'checkbox_when' => '!client.is_person', 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'c', 'pdf_field_type' => 'checkbox', 'checkbox_on_value' => 'si'],
        ]);

        $values = (new PdfFormFiller)->buildFieldValues($module, $this->data([Key::ClientePersonaFisica->value => false]));

        $this->assertSame(['a' => 'Off', 'b' => 'si'], $values);
    }

    public function test_empty_values_do_not_overwrite_the_template(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome],
        ]);

        $this->assertSame([], (new PdfFormFiller)->buildFieldValues($module, $this->data([Key::ClienteNome->value => null])));
    }

    public function test_fill_writes_values_into_the_real_pdf(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNominativo],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text', 'source_key' => Key::PraticaOggi, 'formatter' => ModuleFormatter::DateIt],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica, 'checkbox_on_value' => 'si'],
        ]);

        $pdf = (new PdfFormFiller)->fill($module, $this->data([
            Key::ClienteNominativo->value => 'Niccolò D\'Avéna & Figli',
            Key::PraticaOggi->value => Carbon::parse('2026-10-06'),
            Key::ClientePersonaFisica->value => true,
        ]));

        $this->assertStringStartsWith('%PDF', $pdf);

        $fields = $this->readPdfFields($pdf);
        $this->assertSame('Niccolò D\'Avéna & Figli', $fields['cliente']);
        $this->assertSame('06/10/2026', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);
        $this->assertNull($fields['non_mappato']);
    }

    public function test_flatten_removes_the_form_fields(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome],
        ]);

        $pdf = (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']), flatten: true);

        $this->assertSame([], $this->readPdfFields($pdf));
    }

    public function test_diagnostic_shows_each_field_name_and_checks_checkboxes(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text'],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text'],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'checkbox_on_value' => 'si'],
        ]);

        $fields = $this->readPdfFields((new PdfFormFiller)->fillDiagnostic($module));

        $this->assertSame('cliente', $fields['cliente']);
        $this->assertSame('importo', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);
    }

    public function test_store_diagnostic_writes_to_the_public_disk(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text']]);

        $path = (new PdfFormFiller)->storeDiagnostic($module);

        $this->assertSame('module-diagnostica/modulo-prova.pdf', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_missing_template_throws(): void
    {
        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome]], 'module/inesistente.pdf');

        $this->expectException(PdfFormException::class);
        $this->expectExceptionMessage('module/inesistente.pdf');

        (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']));
    }

    public function test_missing_pdftk_binary_throws_a_dedicated_exception(): void
    {
        config(['services.pdftk.binary' => '/percorso/inesistente/pdftk']);

        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome]]);

        $this->expectException(PdfFormException::class);

        (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']));
    }
}
```

- [ ] **Step 3: Eseguire il test e verificare che fallisca**

Run: `php artisan test --compact tests/Feature/PdfFormFillerTest.php`
Expected: FAIL (classi inesistenti).

- [ ] **Step 4: Aggiungere la configurazione**

In [config/services.php](../../../config/services.php), prima della `];` finale dell'array:

```php
    'pdftk' => [
        'binary' => env('PDFTK_BINARY', 'pdftk'),
    ],
```

- [ ] **Step 5: Implementare l'eccezione**

`app/Services/PdfFormException.php`:

```php
<?php

namespace App\Services;

use RuntimeException;

class PdfFormException extends RuntimeException
{
    public static function missingTemplate(string $path): self
    {
        return new self("Il modulo PDF non esiste su disco: {$path}");
    }

    public static function pdftkFailed(string $details): self
    {
        return new self('Compilazione PDF non disponibile (pdftk): '.trim($details));
    }
}
```

- [ ] **Step 6: Implementare `PdfFormFiller`**

`app/Services/PdfFormFiller.php`:

```php
<?php

namespace App\Services;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;

/**
 * Compila i campi AcroForm di un modulo PDF con pdftk (fill_form).
 */
class PdfFormFiller
{
    /**
     * Apre un PDF con il binario pdftk configurato (config services.pdftk.binary).
     */
    public function open(string $absolutePath): Pdf
    {
        return new Pdf($absolutePath, ['command' => config('services.pdftk.binary', 'pdftk')]);
    }

    /**
     * @return array<string, string> nome campo PDF => valore da scrivere
     */
    public function buildFieldValues(PdfModule $module, ResolvedModuleData $data): array
    {
        $values = [];

        foreach ($module->fields as $field) {
            if ($field->isCheckbox()) {
                $checked = $this->isCheckboxChecked($field, $data);

                if ($checked !== null) {
                    $values[$field->pdf_field_name] = $checked ? ($field->checkbox_on_value ?: 'Yes') : 'Off';
                }

                continue;
            }

            if ($field->source_key === null) {
                continue;
            }

            $text = ModuleFormatter::format($data->get($field->source_key), $field->formatter);

            if ($text !== '') {
                $values[$field->pdf_field_name] = $text;
            }
        }

        return $values;
    }

    /**
     * @return string contenuto binario del PDF compilato
     */
    public function fill(PdfModule $module, ResolvedModuleData $data, bool $flatten = false): string
    {
        return $this->render($module, $this->buildFieldValues($module, $data), $flatten);
    }

    /**
     * PDF diagnostico: ogni campo testo mostra il proprio nome, le checkbox sono spuntate,
     * cosi' chi mappa vede dove sta ciascun campo sul modulo.
     */
    public function fillDiagnostic(PdfModule $module): string
    {
        $values = [];

        foreach ($module->fields as $field) {
            $values[$field->pdf_field_name] = $field->isCheckbox()
                ? ($field->checkbox_on_value ?: 'Yes')
                : $field->pdf_field_name;
        }

        return $this->render($module, $values, false);
    }

    /**
     * Scrive il PDF diagnostico sul disco public e ritorna il percorso relativo.
     * Contiene solo nomi di campo, nessun dato personale.
     */
    public function storeDiagnostic(PdfModule $module): string
    {
        $path = 'module-diagnostica/'.Str::slug($module->name).'.pdf';

        Storage::disk('public')->put($path, $this->fillDiagnostic($module));

        return $path;
    }

    /**
     * Esito della casella: true/false se configurata, null se non c'e' nulla da fare.
     * `checkbox_when` (con `!` iniziale per negare) ha la precedenza su `source_key`.
     */
    private function isCheckboxChecked(PdfModuleField $field, ResolvedModuleData $data): ?bool
    {
        if (filled($field->checkbox_when)) {
            $negate = str_starts_with($field->checkbox_when, '!');
            $key = ModuleSourceKey::tryFrom(ltrim($field->checkbox_when, '!'));

            return $key === null ? null : ($data->isTruthy($key) !== $negate);
        }

        return $field->source_key instanceof ModuleSourceKey ? $data->isTruthy($field->source_key) : null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(PdfModule $module, array $values, bool $flatten): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($module->file_path)) {
            throw PdfFormException::missingTemplate($module->file_path);
        }

        if ($values === [] && ! $flatten) {
            return $disk->get($module->file_path);
        }

        $pdf = $this->open($disk->path($module->file_path));

        if ($values !== []) {
            $pdf->fillForm($values);
        }

        $flatten ? $pdf->flatten() : $pdf->needAppearances();

        $content = $pdf->toString();

        if ($content === false) {
            throw PdfFormException::pdftkFailed($pdf->getError());
        }

        return $content;
    }
}
```

- [ ] **Step 7: Eseguire i test**

Run: `php artisan test --compact tests/Feature/PdfFormFillerTest.php`
Expected: tutti PASS (i test con `pdftk` reale girano perché è installato). Se `test_fill_writes_values_into_the_real_pdf` fallisce sul valore della checkbox o sui caratteri speciali, ispezionare con `pdftk <file> dump_data_fields_utf8` e correggere `render()` (es. passare `fillForm($values, 'UTF-8', true, 'fdf')`), non indebolire l'asserzione.

- [ ] **Step 8: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/PdfFormException.php app/Services/PdfFormFiller.php config/services.php tests/Concerns tests/Feature/PdfFormFillerTest.php tests/Fixtures
git commit -m "feat: PdfFormFiller compila i campi AcroForm con pdftk"
```

---

### Task 5: `PdfFieldSynchronizer` e comando `modules:sync-fields`

**Files:**
- Create: `app/Services/PdfFieldSynchronizer.php`
- Create: `app/Console/Commands/SyncPdfModuleFields.php` (via `php artisan make:command SyncPdfModuleFields --command=modules:sync-fields --no-interaction`, poi sostituire il contenuto)
- Test: `tests/Feature/PdfFieldSynchronizerTest.php`

**Interfaces:**
- Consumes: `PdfFormFiller::open()`, `PdfFormFiller::storeDiagnostic()`, `PdfModule`, `PdfModuleField` (Task 2/4).
- Produces:
  - `PdfFieldSynchronizer::MODULE_DIRECTORY = 'module'`.
  - `PdfFieldSynchronizer::discoverFiles(): array<int, string>` (percorsi relativi ai `.pdf` in `module/`, ordinati).
  - `PdfFieldSynchronizer::sync(string $relativePath): array{module: PdfModule, created: array<int, string>, removed: array<int, string>}` (idempotente; non tocca `source_key`/`formatter` esistenti; non cancella mai righe).
  - `PdfFieldSynchronizer::moduleNameFromPath(string $path): string`.
  - Comando `modules:sync-fields {--diagnostic}`: exit code 0 se tutti i file sono ok, 1 altrimenti.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/Feature/PdfFieldSynchronizerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Services\PdfFieldSynchronizer;
use App\Services\PdfFormException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfFieldSynchronizerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    public function test_it_discovers_only_pdf_files_in_the_module_directory(): void
    {
        Storage::disk('public')->put('module/appunti.txt', 'x');
        Storage::disk('public')->put('module/altra-cartella/nascosto.pdf', $this->fixturePdfContents());
        Storage::disk('public')->put('altrove.pdf', $this->fixturePdfContents());

        $this->assertSame(['module/modulo-prova.pdf'], app(PdfFieldSynchronizer::class)->discoverFiles());
    }

    public function test_module_name_is_derived_from_the_file_name(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);

        $this->assertSame(
            'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile',
            $synchronizer->moduleNameFromPath('module/Races VERS. 03_2026 - Fascicolo completo retail  CQ compilabile_compressed.pdf'),
        );
    }

    public function test_first_sync_creates_the_module_and_its_fields(): void
    {
        $result = app(PdfFieldSynchronizer::class)->sync('module/modulo-prova.pdf');

        $this->assertSame('modulo-prova', $result['module']->name);
        $this->assertEqualsCanonicalizing(['cliente', 'importo', 'consenso', 'non_mappato'], $result['created']);
        $this->assertSame([], $result['removed']);

        $checkbox = PdfModuleField::where('pdf_field_name', 'consenso')->sole();
        $this->assertSame('checkbox', $checkbox->pdf_field_type);
        $this->assertSame('si', $checkbox->checkbox_on_value);

        $this->assertSame('text', PdfModuleField::where('pdf_field_name', 'cliente')->sole()->pdf_field_type);
    }

    public function test_sync_is_idempotent_and_keeps_manual_mappings(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);
        $synchronizer->sync('module/modulo-prova.pdf');

        PdfModuleField::where('pdf_field_name', 'cliente')->update(['source_key' => 'client.name', 'formatter' => 'upper']);

        $second = $synchronizer->sync('module/modulo-prova.pdf');

        $this->assertSame([], $second['created']);
        $this->assertSame(1, PdfModule::count());
        $this->assertSame(4, PdfModuleField::count());

        $field = PdfModuleField::where('pdf_field_name', 'cliente')->sole();
        $this->assertSame('client.name', $field->source_key->value);
        $this->assertSame('upper', $field->formatter->value);
    }

    public function test_fields_missing_from_the_pdf_are_reported_but_not_deleted(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);
        $module = $synchronizer->sync('module/modulo-prova.pdf')['module'];
        PdfModuleField::create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'vecchio_campo']);

        $result = $synchronizer->sync('module/modulo-prova.pdf');

        $this->assertSame(['vecchio_campo'], $result['removed']);
        $this->assertDatabaseHas('pdf_module_fields', ['pdf_field_name' => 'vecchio_campo']);
    }

    public function test_sync_of_a_missing_file_throws(): void
    {
        $this->expectException(PdfFormException::class);

        app(PdfFieldSynchronizer::class)->sync('module/inesistente.pdf');
    }

    public function test_command_syncs_every_module_and_reports_the_outcome(): void
    {
        $this->artisan('modules:sync-fields')
            ->expectsOutputToContain('modulo-prova')
            ->expectsOutputToContain('4 nuovi')
            ->assertExitCode(0);

        $this->assertSame(4, PdfModuleField::count());
    }

    public function test_command_with_diagnostic_writes_a_pdf_showing_the_field_names(): void
    {
        $this->artisan('modules:sync-fields', ['--diagnostic' => true])->assertExitCode(0);

        Storage::disk('public')->assertExists('module-diagnostica/modulo-prova.pdf');

        $fields = $this->readPdfFields(Storage::disk('public')->get('module-diagnostica/modulo-prova.pdf'));
        $this->assertSame('cliente', $fields['cliente']);
        $this->assertSame('si', $fields['consenso']);
    }

    public function test_command_exits_with_failure_when_a_pdf_cannot_be_read(): void
    {
        Storage::disk('public')->put('module/rotto.pdf', 'questo non e un pdf');

        $this->artisan('modules:sync-fields')
            ->expectsOutputToContain('rotto')
            ->assertExitCode(1);

        $this->assertDatabaseHas('pdf_modules', ['file_path' => 'module/modulo-prova.pdf']);
    }
}
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php artisan test --compact tests/Feature/PdfFieldSynchronizerTest.php`
Expected: FAIL (classe/comando inesistenti).

- [ ] **Step 3: Implementare il sincronizzatore**

`app/Services/PdfFieldSynchronizer.php`:

```php
<?php

namespace App\Services;

use App\Models\PdfModule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Allinea `pdf_modules` / `pdf_module_fields` ai PDF presenti in storage/app/public/module.
 * Non modifica mai le mappature gia' fatte e non cancella mai righe.
 */
class PdfFieldSynchronizer
{
    public const MODULE_DIRECTORY = 'module';

    public function __construct(private PdfFormFiller $filler) {}

    /**
     * @return array<int, string> percorsi relativi dei PDF, ordinati
     */
    public function discoverFiles(): array
    {
        $files = array_filter(
            Storage::disk('public')->files(self::MODULE_DIRECTORY),
            fn (string $path) => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf',
        );

        sort($files);

        return array_values($files);
    }

    /**
     * @return array{module: PdfModule, created: array<int, string>, removed: array<int, string>}
     */
    public function sync(string $relativePath): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            throw PdfFormException::missingTemplate($relativePath);
        }

        $pdf = $this->filler->open($disk->path($relativePath));
        $dataFields = $pdf->getDataFields();

        if ($dataFields === false) {
            throw PdfFormException::pdftkFailed($pdf->getError());
        }

        $module = PdfModule::firstOrCreate(
            ['file_path' => $relativePath],
            ['name' => $this->moduleNameFromPath($relativePath)],
        );

        $found = [];
        $created = [];

        foreach ($dataFields->__toArray() as $field) {
            $name = $field['FieldName'] ?? null;
            $type = $field['FieldType'] ?? null;

            if ($name === null || ! in_array($type, ['Text', 'Button'], true)) {
                continue;
            }

            $states = Arr::wrap($field['FieldStateOption'] ?? []);

            // Un Button senza stati e' un pulsante (stampa, svuota campi), non una casella.
            if ($type === 'Button' && $states === []) {
                continue;
            }

            $isCheckbox = $type === 'Button';
            $onValue = $isCheckbox
                ? Arr::first($states, fn (string $state) => strcasecmp($state, 'Off') !== 0)
                : null;

            $row = $module->fields()->firstOrCreate(
                ['pdf_field_name' => $name],
                ['pdf_field_type' => $isCheckbox ? 'checkbox' : 'text', 'checkbox_on_value' => $onValue],
            );

            if ($row->wasRecentlyCreated) {
                $created[] = $name;
            }

            $found[] = $name;
        }

        $removed = $module->fields()
            ->whereNotIn('pdf_field_name', $found)
            ->pluck('pdf_field_name')
            ->all();

        return ['module' => $module, 'created' => $created, 'removed' => $removed];
    }

    public function moduleNameFromPath(string $path): string
    {
        $name = str_replace('_compressed', '', pathinfo($path, PATHINFO_FILENAME));

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
```

- [ ] **Step 4: Implementare il comando**

Run: `php artisan make:command SyncPdfModuleFields --command=modules:sync-fields --no-interaction`, poi `app/Console/Commands/SyncPdfModuleFields.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\PdfFieldSynchronizer;
use App\Services\PdfFormException;
use App\Services\PdfFormFiller;
use Illuminate\Console\Command;

class SyncPdfModuleFields extends Command
{
    protected $signature = 'modules:sync-fields {--diagnostic : Genera per ogni modulo un PDF in cui ogni campo mostra il proprio nome}';

    protected $description = 'Legge i campi AcroForm dei PDF in storage/app/public/module e li registra in pdf_module_fields';

    public function handle(PdfFieldSynchronizer $synchronizer, PdfFormFiller $filler): int
    {
        $files = $synchronizer->discoverFiles();

        if ($files === []) {
            $this->warn('Nessun PDF trovato in storage/app/public/'.PdfFieldSynchronizer::MODULE_DIRECTORY);

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($files as $file) {
            try {
                $result = $synchronizer->sync($file);
            } catch (PdfFormException $exception) {
                $failed = true;
                $this->error(basename($file).': '.$exception->getMessage());

                continue;
            }

            $module = $result['module'];

            $this->line(sprintf(
                '%s: %d nuovi, %d non piu\' presenti nel PDF',
                $module->name,
                count($result['created']),
                count($result['removed']),
            ));

            if ($result['removed'] !== []) {
                $this->warn('  campi spariti: '.implode(', ', $result['removed']));
            }

            if ($this->option('diagnostic')) {
                $this->line('  diagnostico: storage/app/public/'.$filler->storeDiagnostic($module->load('fields')));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
```

- [ ] **Step 5: Eseguire i test**

Run: `php artisan test --compact tests/Feature/PdfFieldSynchronizerTest.php`
Expected: tutti PASS. Se `test_command_exits_with_failure...` non produce errore sul file non-PDF (pdftk potrebbe restituire output vuoto con exit 0), far lanciare l'eccezione quando `getDataFields()` non è un `DataFields` valido **e** il file non inizia con `%PDF`: aggiungere in `sync()`, prima di `open()`, `if (! str_starts_with($disk->get($relativePath), '%PDF')) { throw PdfFormException::pdftkFailed('file non PDF: '.$relativePath); }`.

- [ ] **Step 6: Eseguire il comando sui PDF reali (verifica manuale)**

Run: `php artisan db:seed --class=PdfModuleSeeder --no-interaction && php artisan modules:sync-fields --diagnostic`
Expected: 13 righe, una per PDF, con conteggi di campi coerenti con l'analisi (es. Prestiti Personali ~49 testo + checkbox; i pulsanti `stampa`/`svuota campi` esclusi) e percorsi dei PDF diagnostici in `storage/app/public/module-diagnostica/`. Aprire un PDF diagnostico per verificare che i nomi compaiano sui campi.

- [ ] **Step 7: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/PdfFieldSynchronizer.php app/Console/Commands/SyncPdfModuleFields.php tests/Feature/PdfFieldSynchronizerTest.php
git commit -m "feat: comando modules:sync-fields e sincronizzatore dei campi PDF"
```

---

### Task 6: `ModuleSuggester` e `PraticaModuleGenerator`

**Files:**
- Create: `app/Services/ModuleSuggester.php`
- Create: `app/Services/PraticaModuleGenerator.php`
- Test: `tests/Feature/ModuleSuggesterTest.php`
- Test: `tests/Feature/PraticaModuleGeneratorTest.php`

**Interfaces:**
- Consumes: `ModuleDataResolver::resolve()`, `ResolvedModuleData::missingAmong()`, `PdfFormFiller::fill()`, `PdfModule`, `PdfModuleField::referencedKeys()`.
- Produces:
  - `ModuleSuggester::suggest(Pratica $pratica, Client $client): Collection<int, PdfModule>` (moduli attivi pertinenti, ordinati per nome).
  - `PraticaModuleGenerator::missingByModule(Pratica $pratica, Client $client, iterable $modules): array<int, array<int, ModuleSourceKey>>` (chiave = id modulo).
  - `PraticaModuleGenerator::generate(Pratica $pratica, Client $client, iterable $modules, ?User $user): Collection<int, Document>`: genera **tutti** i PDF prima di salvare; se uno fallisce lancia `PdfFormException` senza creare alcun `Document`. Ogni `Document` è legato alla pratica (`documents()` morph), ha `status = 'caricato'`, `uploaded_by`/`created_by`, e il PDF nella media collection `documents` (disco `public`). Scrive un'attività `moduli_generati` (log `moduli_pdf`) con soggetto il **Client** e proprietà `pratica_id`, `codice_pratica`, `moduli`, `documenti` (mai valori sensibili): `activity_log.subject_id` è `bigint` e l'id della pratica è un UUID.

- [ ] **Step 1: Scrivere i test che falliscono**

`tests/Feature/ModuleSuggesterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\PdfModuleClientScope;
use App\Models\Client;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleSuggester;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModuleSuggesterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_suggests_active_modules_matching_product_and_client_type_sorted_by_name(): void
    {
        PdfModule::factory()->create(['name' => 'B Prestiti retail', 'tipi_prodotto' => ['Prestito'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'A Privacy', 'tipi_prodotto' => null, 'client_scope' => PdfModuleClientScope::Entrambi]);
        PdfModule::factory()->create(['name' => 'C Corporate', 'tipi_prodotto' => ['Prestito'], 'client_scope' => PdfModuleClientScope::PersonaGiuridica]);
        PdfModule::factory()->create(['name' => 'D Mutui', 'tipi_prodotto' => ['Mutuo'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'E Disattivo', 'tipi_prodotto' => null, 'is_active' => false]);

        $suggested = app(ModuleSuggester::class)->suggest(
            new Pratica(['tipo_prodotto' => 'Prestito']),
            new Client(['is_person' => true]),
        );

        $this->assertSame(['A Privacy', 'B Prestiti retail'], $suggested->pluck('name')->all());
    }

    public function test_company_client_gets_corporate_modules(): void
    {
        PdfModule::factory()->create(['name' => 'Retail', 'tipi_prodotto' => ['Aziendale'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'Corporate', 'tipi_prodotto' => ['Aziendale'], 'client_scope' => PdfModuleClientScope::PersonaGiuridica]);

        $suggested = app(ModuleSuggester::class)->suggest(
            new Pratica(['tipo_prodotto' => 'Aziendale']),
            new Client(['is_person' => false]),
        );

        $this->assertSame(['Corporate'], $suggested->pluck('name')->all());
    }
}
```

`tests/Feature/PraticaModuleGeneratorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use App\Services\PdfFormException;
use App\Services\PraticaModuleGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PraticaModuleGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    private function pratica(array $attributes = []): Pratica
    {
        return Pratica::create(array_merge([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-2026-001',
            'amount' => 15000,
            'tipo_prodotto' => 'Prestito',
        ], $attributes));
    }

    private function client(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
            'iban' => 'IT60X0542811101000000123456',
        ], $attributes));
    }

    private function module(string $path = 'module/modulo-prova.pdf', string $name = 'Modulo prova'): PdfModule
    {
        $module = PdfModule::factory()->create(['name' => $name, 'file_path' => $path]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente', 'source_key' => Key::ClienteNominativo]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'importo', 'source_key' => Key::PraticaImporto, 'formatter' => 'money_it']);
        PdfModuleField::factory()->checkbox('si')->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'consenso', 'source_key' => Key::ClientePersonaFisica]);

        return $module->load('fields');
    }

    public function test_generates_a_document_per_module_with_the_filled_pdf_attached(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();
        $module = $this->module();

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, [$module], $user);

        $this->assertCount(1, $documents);

        $document = $documents->first();
        $this->assertSame($pratica->id, $document->documentable_id);
        $this->assertSame($pratica::class, $document->documentable_type);
        $this->assertSame('Modulo prova - P-2026-001', $document->name);
        $this->assertSame('caricato', $document->status instanceof \BackedEnum ? $document->status->value : $document->status);
        $this->assertSame($user->id, $document->uploaded_by);

        $media = $document->getFirstMedia('documents');
        $this->assertNotNull($media);
        $this->assertSame('application/pdf', $media->mime_type);

        $fields = $this->readPdfFields(file_get_contents($media->getPath()));
        $this->assertSame('Rossi Mario', $fields['cliente']);
        $this->assertSame('15.000,00', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);

        $this->assertTrue($pratica->documents()->whereKey($document->getKey())->exists());
    }

    public function test_logs_the_activity_on_the_client_without_sensitive_values(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, [$this->module()], $user);

        $activity = Activity::query()->where('event', 'moduli_generati')->sole();

        $this->assertSame('moduli_pdf', $activity->log_name);
        $this->assertTrue($activity->subject->is($client));
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame($pratica->id, $activity->getProperty('pratica_id'));
        $this->assertSame('P-2026-001', $activity->getProperty('codice_pratica'));
        $this->assertSame(['Modulo prova'], $activity->getProperty('moduli'));
        $this->assertSame([$documents->first()->getKey()], $activity->getProperty('documenti'));
        $this->assertStringNotContainsString('IT60X0542811101000000123456', json_encode($activity->properties));
    }

    public function test_a_failing_module_aborts_everything_without_leaving_partial_documents(): void
    {
        $good = $this->module();
        $broken = $this->module('module/non-esiste.pdf', 'Modulo rotto');

        try {
            app(PraticaModuleGenerator::class)->generate($this->pratica(), $this->client(), [$good, $broken], User::factory()->create());
            $this->fail('Doveva lanciare PdfFormException.');
        } catch (PdfFormException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Activity::query()->where('event', 'moduli_generati')->count());
    }

    public function test_generating_the_same_module_twice_keeps_both_documents(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();
        $module = $this->module();
        $generator = app(PraticaModuleGenerator::class);

        $generator->generate($pratica, $client, [$module], $user);
        $generator->generate($pratica, $client, [$module], $user);

        $this->assertSame(2, $pratica->documents()->count());
    }

    public function test_pratica_without_code_or_amount_still_generates_and_names_the_file_after_the_id(): void
    {
        $pratica = $this->pratica(['codice_pratica' => null, 'amount' => null]);

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $this->client(), [$this->module()], null);

        $document = $documents->sole();
        $this->assertSame('Modulo prova - '.$pratica->id, $document->name);
        $this->assertStringContainsString($pratica->id, $document->getFirstMedia('documents')->file_name);

        $fields = $this->readPdfFields(file_get_contents($document->getFirstMedia('documents')->getPath()));
        $this->assertNull($fields['importo']);
    }

    public function test_missing_by_module_lists_the_unavailable_source_keys(): void
    {
        $client = $this->client(['iban' => null]);
        $module = $this->module();
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'extra', 'source_key' => Key::ClienteIban]);
        $module->load('fields');

        $missing = app(PraticaModuleGenerator::class)->missingByModule($this->pratica(), $client, [$module]);

        $this->assertSame([Key::ClienteIban], $missing[$module->id]);
    }
}
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `php artisan test --compact tests/Feature/ModuleSuggesterTest.php tests/Feature/PraticaModuleGeneratorTest.php`
Expected: FAIL (classi inesistenti).

- [ ] **Step 3: Implementare `ModuleSuggester`**

`app/Services/ModuleSuggester.php`:

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use Illuminate\Support\Collection;

class ModuleSuggester
{
    /**
     * Moduli attivi pertinenti per il tipo di prodotto della pratica e il tipo di cliente.
     *
     * @return Collection<int, PdfModule>
     */
    public function suggest(Pratica $pratica, Client $client): Collection
    {
        return PdfModule::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (PdfModule $module) => $module->appliesTo($pratica->tipo_prodotto, (bool) $client->is_person))
            ->values();
    }
}
```

- [ ] **Step 4: Implementare `PraticaModuleGenerator`**

`app/Services/PraticaModuleGenerator.php`:

```php
<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ModuleSourceKey;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestra la generazione dei moduli PDF di una pratica: dati, compilazione,
 * archiviazione come Document della pratica e traccia nell'activity log.
 */
class PraticaModuleGenerator
{
    public function __construct(
        private ModuleDataResolver $resolver,
        private PdfFormFiller $filler,
    ) {}

    /**
     * Chiavi dati senza valore, per ogni modulo (indicizzato per id modulo): serve all'anteprima.
     *
     * @param  iterable<PdfModule>  $modules
     * @return array<int, array<int, ModuleSourceKey>>
     */
    public function missingByModule(Pratica $pratica, Client $client, iterable $modules): array
    {
        $data = $this->resolver->resolve($pratica, $client);
        $missing = [];

        foreach ($modules as $module) {
            $keys = $module->fields
                ->flatMap(fn (PdfModuleField $field) => $field->referencedKeys())
                ->all();

            $missing[$module->getKey()] = $data->missingAmong($keys);
        }

        return $missing;
    }

    /**
     * Genera tutti i PDF prima di salvare qualsiasi cosa: se uno fallisce non resta nulla.
     *
     * @param  iterable<PdfModule>  $modules
     * @return Collection<int, Document>
     *
     * @throws PdfFormException
     */
    public function generate(Pratica $pratica, Client $client, iterable $modules, ?User $user): Collection
    {
        $modules = collect($modules)->values();
        $data = $this->resolver->resolve($pratica, $client);

        $rendered = $modules->map(fn (PdfModule $module) => [
            'module' => $module,
            'content' => $this->filler->fill($module, $data),
        ]);

        $reference = $pratica->codice_pratica ?: (string) $pratica->getKey();

        $documents = DB::transaction(function () use ($rendered, $pratica, $reference, $user): Collection {
            return $rendered->map(fn (array $item) => $this->store($pratica, $item['module'], $item['content'], $reference, $user));
        });

        if ($documents->isNotEmpty()) {
            activity('moduli_pdf')
                ->performedOn($client)
                ->causedBy($user)
                ->event('moduli_generati')
                ->withProperties([
                    'pratica_id' => $pratica->getKey(),
                    'codice_pratica' => $pratica->codice_pratica,
                    'moduli' => $modules->pluck('name')->all(),
                    'documenti' => $documents->map(fn (Document $document) => $document->getKey())->all(),
                ])
                ->log('Moduli PDF generati per la pratica '.$reference);
        }

        return $documents;
    }

    private function store(Pratica $pratica, PdfModule $module, string $content, string $reference, ?User $user): Document
    {
        /** @var Document $document */
        $document = $pratica->documents()->create([
            'name' => $module->name.' - '.$reference,
            'status' => DocumentStatus::UPLOADED->value,
            'spatie_collection' => 'documents',
            'uploaded_by' => $user?->getKey(),
            'created_by' => $user?->getKey(),
        ]);

        $document->addMediaFromString($content)
            ->usingFileName(Str::slug($module->name.' '.$reference).'.pdf')
            ->toMediaCollection('documents');

        return $document;
    }
}
```

- [ ] **Step 5: Eseguire i test**

Run: `php artisan test --compact tests/Feature/ModuleSuggesterTest.php tests/Feature/PraticaModuleGeneratorTest.php`
Expected: tutti PASS. Se `uploaded_by`/`status` non coincidono con i cast reali di `Document` (`status` potrebbe tornare come enum), l'asserzione dello Step 1 gestisce già entrambi i casi; non cambiare `DocumentStatus::UPLOADED->value`.

- [ ] **Step 6: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Services/ModuleSuggester.php app/Services/PraticaModuleGenerator.php tests/Feature/ModuleSuggesterTest.php tests/Feature/PraticaModuleGeneratorTest.php
git commit -m "feat: suggerimento moduli e generazione documenti per pratica"
```

---

### Task 7: Risorsa Filament `PdfModuleResource` (mappatura dei campi)

**Files:**
- Create (via `php artisan make:filament-resource PdfModule --no-interaction`, poi sovrascrivere i file elencati ed eliminare `Pages/CreatePdfModule.php`): `app/Filament/Resources/PdfModules/PdfModuleResource.php`
- Create/Modify: `app/Filament/Resources/PdfModules/Pages/ListPdfModules.php`, `Pages/EditPdfModule.php`, `Schemas/PdfModuleForm.php`, `Tables/PdfModulesTable.php`
- Create: `app/Filament/Resources/PdfModules/RelationManagers/FieldsRelationManager.php`
- Test: `tests/Feature/PdfModuleResourceTest.php`

**Interfaces:**
- Consumes: `PdfModule`, `PdfModuleField`, `ModuleSourceKey`, `ModuleFormatter`, `PdfModuleClientScope`, `PdfFormFiller::storeDiagnostic()`, `Tipoprodotto` (per i suggerimenti dei prodotti).
- Produces: pagine `index` ed `edit` (nessuna `create`: i moduli nascono da `modules:sync-fields`); azione header `diagnostica` su `EditPdfModule` che salva il PDF diagnostico sul disco `public` e mostra una notifica con link.

- [ ] **Step 1: Generare lo scheletro**

Run: `php artisan make:filament-resource PdfModule --no-interaction`
Poi eliminare `app/Filament/Resources/PdfModules/Pages/CreatePdfModule.php` (e, se generato, `ViewPdfModule`). I file seguenti vanno **sovrascritti per intero**.

- [ ] **Step 2: Scrivere il test che fallisce**

`tests/Feature/PdfModuleResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey;
use App\Enums\PdfModuleClientScope;
use App\Filament\Resources\PdfModules\Pages\EditPdfModule;
use App\Filament\Resources\PdfModules\Pages\ListPdfModules;
use App\Filament\Resources\PdfModules\PdfModuleResource;
use App\Filament\Resources\PdfModules\RelationManagers\FieldsRelationManager;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfModuleResourceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    public function test_resource_is_registered_without_a_create_page(): void
    {
        $this->assertContains(PdfModuleResource::class, filament()->getPanel('admin')->getResources());
        $this->assertArrayNotHasKey('create', PdfModuleResource::getPages());
        $this->assertFalse(PdfModuleResource::canCreate());
    }

    public function test_list_shows_modules_with_their_field_counts(): void
    {
        $modules = PdfModule::factory()->count(2)->create();
        PdfModuleField::factory()->count(3)->create(['pdf_module_id' => $modules[0]->id]);

        Livewire::test(ListPdfModules::class)
            ->assertCanSeeTableRecords($modules)
            ->assertTableColumnStateSet('fields_count', 3, $modules[0]);
    }

    public function test_edit_form_updates_scope_products_and_active_flag(): void
    {
        $module = PdfModule::factory()->create();

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->fillForm([
                'name' => 'Fascicolo Prestiti',
                'client_scope' => PdfModuleClientScope::PersonaFisica->value,
                'tipi_prodotto' => ['Prestito', 'Microcredito'],
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $module->refresh();

        $this->assertSame('Fascicolo Prestiti', $module->name);
        $this->assertSame(PdfModuleClientScope::PersonaFisica, $module->client_scope);
        $this->assertSame(['Prestito', 'Microcredito'], $module->tipi_prodotto);
        $this->assertFalse($module->is_active);
    }

    public function test_fields_relation_manager_lists_fields_and_offers_the_whitelist_options(): void
    {
        $module = PdfModule::factory()->create();
        $fields = PdfModuleField::factory()->count(2)->create(['pdf_module_id' => $module->id]);

        $options = collect(ModuleSourceKey::cases())
            ->mapWithKeys(fn (ModuleSourceKey $case) => [$case->value => $case->getLabel()])
            ->all();

        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $module, 'pageClass' => EditPdfModule::class])
            ->assertCanSeeTableRecords($fields)
            ->assertTableSelectColumnHasOptions('source_key', $options, $fields->first());
    }

    public function test_fields_relation_manager_filters_unmapped_fields(): void
    {
        $module = PdfModule::factory()->create();
        $mapped = PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'source_key' => ModuleSourceKey::ClienteNome]);
        $unmapped = PdfModuleField::factory()->create(['pdf_module_id' => $module->id]);

        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $module, 'pageClass' => EditPdfModule::class])
            ->filterTable('non_mappati')
            ->assertCanSeeTableRecords([$unmapped])
            ->assertCanNotSeeTableRecords([$mapped]);
    }

    public function test_diagnostic_action_stores_the_pdf_and_notifies(): void
    {
        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());

        $module = PdfModule::factory()->create(['name' => 'Modulo prova', 'file_path' => 'module/modulo-prova.pdf']);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente']);

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->callAction('diagnostica')
            ->assertNotified('PDF diagnostico pronto');

        Storage::disk('public')->assertExists('module-diagnostica/modulo-prova.pdf');
    }

    public function test_diagnostic_action_reports_an_error_when_the_pdf_is_missing(): void
    {
        Storage::fake('public');

        $module = PdfModule::factory()->create(['file_path' => 'module/non-esiste.pdf']);

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->callAction('diagnostica')
            ->assertNotified('Compilazione non disponibile');
    }
}
```

- [ ] **Step 3: Eseguire il test e verificare che fallisca**

Run: `php artisan test --compact tests/Feature/PdfModuleResourceTest.php`
Expected: FAIL.

- [ ] **Step 4: Scrivere la risorsa e le pagine**

`app/Filament/Resources/PdfModules/PdfModuleResource.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules;

use App\Filament\Resources\PdfModules\Pages\EditPdfModule;
use App\Filament\Resources\PdfModules\Pages\ListPdfModules;
use App\Filament\Resources\PdfModules\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\PdfModules\Schemas\PdfModuleForm;
use App\Filament\Resources\PdfModules\Tables\PdfModulesTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\PdfModule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PdfModuleResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = PdfModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Moduli PDF';

    protected static ?string $modelLabel = 'Modulo PDF';

    protected static ?string $pluralModelLabel = 'Moduli PDF';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * I moduli nascono da `php artisan modules:sync-fields`, non dalla UI.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PdfModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PdfModulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPdfModules::route('/'),
            'edit' => EditPdfModule::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/PdfModules/Schemas/PdfModuleForm.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules\Schemas;

use App\Enums\PdfModuleClientScope;
use App\Models\Tipoprodotto;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PdfModuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modulo')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('file_path')
                            ->label('File')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('version')
                            ->label('Versione')
                            ->maxLength(255),
                        Select::make('client_scope')
                            ->label('Ambito cliente')
                            ->options(PdfModuleClientScope::class)
                            ->required(),
                        TagsInput::make('tipi_prodotto')
                            ->label('Tipi di prodotto')
                            ->helperText('Vuoto = pertinente per qualunque prodotto. Usare i nomi dei tipi prodotto della pratica.')
                            ->suggestions(fn (): array => Tipoprodotto::query()->orderBy('name')->pluck('name')->filter()->all()),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
```

`app/Filament/Resources/PdfModules/Tables/PdfModulesTable.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PdfModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Modulo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client_scope')
                    ->label('Ambito')
                    ->badge(),
                TextColumn::make('tipi_prodotto')
                    ->label('Prodotti')
                    ->badge()
                    ->placeholder('Tutti'),
                TextColumn::make('fields_count')
                    ->label('Campi')
                    ->counts('fields'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

`app/Filament/Resources/PdfModules/Pages/ListPdfModules.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules\Pages;

use App\Filament\Resources\PdfModules\PdfModuleResource;
use Filament\Resources\Pages\ListRecords;

class ListPdfModules extends ListRecords
{
    protected static string $resource = PdfModuleResource::class;
}
```

`app/Filament/Resources/PdfModules/Pages/EditPdfModule.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules\Pages;

use App\Filament\Resources\PdfModules\PdfModuleResource;
use App\Models\PdfModule;
use App\Services\PdfFormException;
use App\Services\PdfFormFiller;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditPdfModule extends EditRecord
{
    protected static string $resource = PdfModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('diagnostica')
                ->label('Genera PDF diagnostico')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function (PdfModule $record): void {
                    try {
                        $path = app(PdfFormFiller::class)->storeDiagnostic($record->load('fields'));
                    } catch (PdfFormException $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Compilazione non disponibile')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('PDF diagnostico pronto')
                        ->body('Ogni campo mostra il proprio nome: usalo per capire dove sta ciascun campo.')
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('apri')
                                ->label('Apri PDF')
                                ->button()
                                ->url(Storage::disk('public')->url($path), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),
        ];
    }
}
```

`app/Filament/Resources/PdfModules/RelationManagers/FieldsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\PdfModules\RelationManagers;

use App\Enums\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campi del modulo';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pdf_field_name')
            ->defaultSort('id')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('pdf_field_name')
                    ->label('Campo PDF')
                    ->searchable(),
                TextColumn::make('pdf_field_type')
                    ->label('Tipo')
                    ->badge(),
                SelectColumn::make('source_key')
                    ->label('Dato')
                    ->options(ModuleSourceKey::class)
                    ->placeholder('— non compilare —')
                    ->searchableOptions(),
                SelectColumn::make('formatter')
                    ->label('Formato')
                    ->options(ModuleFormatter::class)
                    ->placeholder('— nessuno —'),
                TextInputColumn::make('checkbox_on_value')
                    ->label('Valore "spuntato"'),
                TextInputColumn::make('checkbox_when')
                    ->label('Condizione casella')
                    ->placeholder('es. !client.is_person'),
            ])
            ->filters([
                Filter::make('non_mappati')
                    ->label('Solo non mappati')
                    ->query(fn (Builder $query) => $query->whereNull('source_key')),
            ]);
    }
}
```

> Se `searchableOptions()` non esiste su `SelectColumn` in Filament 5, usare `->searchable()` oppure rimuovere la chiamata (verifica con `mcp__laravel-boost__search-docs` query `SelectColumn searchable`): non cambia il comportamento testato.

- [ ] **Step 5: Eseguire i test**

Run: `php artisan test --compact tests/Feature/PdfModuleResourceTest.php tests/Feature/FilamentResourceDiscoveryTest.php`
Expected: tutti PASS. Poi `php artisan permissions:sync-resources --no-interaction` per registrare la nuova risorsa nel registro piani/permessi (vedi [DEPLOY.md](../../../DEPLOY.md)) e controllare a mano che "Moduli PDF" compaia in Sistema.

- [ ] **Step 6: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Filament/Resources/PdfModules tests/Feature/PdfModuleResourceTest.php
git commit -m "feat: risorsa Filament per la mappatura dei campi dei moduli PDF"
```

---

### Task 8: Azione "Genera moduli" su `EditPratica`

**Files:**
- Create: `app/Filament/Actions/GeneraModuliPraticaAction.php`
- Modify: `app/Filament/Resources/Praticas/Pages/EditPratica.php` (registrare l'azione tra le header actions, prima di `DeleteAction::make()`)
- Test: `tests/Feature/GeneraModuliPraticaActionTest.php`

**Interfaces:**
- Consumes: `ModuleDataResolver::findClient()`, `ModuleSuggester::suggest()`, `PraticaModuleGenerator::missingByModule()/generate()`, `PdfModule`, `PdfFormException`, route `documents.download`.
- Produces: azione `generaModuli` (nome di default), disabilitata con tooltip se il cliente non si trova; modale con `CheckboxList` dei moduli attivi (suggeriti preselezionati, descrizione = dati mancanti); a conferma genera i documenti e mostra una notifica persistente con un link di download per ciascun documento.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/Feature/GeneraModuliPraticaActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class GeneraModuliPraticaActionTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    private function pratica(?string $codiceFiscale): Pratica
    {
        return Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-2026-001',
            'codice_fiscale' => $codiceFiscale,
            'tipo_prodotto' => 'Prestito',
            'amount' => 15000,
        ]);
    }

    private function module(string $name = 'Modulo prova', ?array $tipi = ['Prestito']): PdfModule
    {
        $module = PdfModule::factory()->create(['name' => $name, 'file_path' => 'module/modulo-prova.pdf', 'tipi_prodotto' => $tipi]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente', 'source_key' => Key::ClienteNominativo]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'importo', 'source_key' => Key::ClienteIban]);

        return $module;
    }

    public function test_action_is_disabled_when_the_client_cannot_be_found(): void
    {
        $this->module();

        Livewire::test(EditPratica::class, ['record' => $this->pratica('SCONOSCIUTO000000')->getKey()])
            ->assertActionDisabled('generaModuli');
    }

    public function test_action_generates_the_selected_modules_and_notifies_with_download_links(): void
    {
        $this->skipUnlessPdftk();

        $code = strtoupper(Str::random(16));
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $module = $this->module();

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionEnabled('generaModuli')
            ->callAction('generaModuli', data: ['moduli' => [$module->id]])
            ->assertHasNoActionErrors()
            ->assertNotified('Modulo generato');

        $document = Document::where('documentable_id', $pratica->id)->sole();
        $fields = $this->readPdfFields(file_get_contents($document->getFirstMedia('documents')->getPath()));
        $this->assertSame('Rossi Mario', $fields['cliente']);

        $this->assertTrue(Activity::query()->where('event', 'moduli_generati')->sole()->subject->is($client));
    }

    public function test_only_active_modules_can_be_generated_even_if_an_id_is_tampered(): void
    {
        $this->skipUnlessPdftk();

        $code = strtoupper(Str::random(16));
        Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $inactive = $this->module('Disattivo');
        $inactive->update(['is_active' => false]);
        $active = $this->module('Attivo');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction('generaModuli', data: ['moduli' => [$inactive->id, $active->id]]);

        $this->assertSame(['Attivo - P-2026-001'], Document::where('documentable_id', $pratica->id)->pluck('name')->all());
    }

    public function test_a_missing_template_shows_an_error_and_creates_no_documents(): void
    {
        $code = strtoupper(Str::random(16));
        Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $module = $this->module();
        $module->update(['file_path' => 'module/non-esiste.pdf']);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction('generaModuli', data: ['moduli' => [$module->id]])
            ->assertNotified('Compilazione non disponibile');

        $this->assertSame(0, Document::where('documentable_id', $pratica->id)->count());
    }
}
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php artisan test --compact tests/Feature/GeneraModuliPraticaActionTest.php`
Expected: FAIL (azione inesistente).

- [ ] **Step 3: Implementare l'azione**

`app/Filament/Actions/GeneraModuliPraticaAction.php`:

```php
<?php

namespace App\Filament\Actions;

use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use App\Services\ModuleSuggester;
use App\Services\PdfFormException;
use App\Services\PraticaModuleGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

/**
 * Genera i moduli PDF compilati per la pratica corrente e li archivia tra i suoi documenti.
 */
class GeneraModuliPraticaAction extends Action
{
    /**
     * @var array<string, Client|null>
     */
    private array $clients = [];

    public static function getDefaultName(): ?string
    {
        return 'generaModuli';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Genera moduli')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->modalHeading('Genera moduli PDF')
            ->modalDescription('Scegli i moduli da compilare con i dati della pratica e del cliente.')
            ->modalSubmitActionLabel('Genera')
            ->disabled(fn (Pratica $record): bool => $this->clientFor($record) === null)
            ->tooltip(fn (Pratica $record): ?string => $this->clientFor($record) === null
                ? 'Cliente non trovato per il codice fiscale della pratica'
                : null)
            ->schema(fn (Pratica $record): array => $this->formSchema($record))
            ->action(fn (Pratica $record, array $data) => $this->generate($record, $data['moduli'] ?? []));
    }

    private function clientFor(Pratica $pratica): ?Client
    {
        return $this->clients[$pratica->getKey()] ??= app(ModuleDataResolver::class)->findClient($pratica);
    }

    /**
     * @return array<int, CheckboxList>
     */
    private function formSchema(Pratica $pratica): array
    {
        $client = $this->clientFor($pratica);

        if ($client === null) {
            return [];
        }

        $modules = PdfModule::query()->active()->with('fields')->orderBy('name')->get();
        $missing = app(PraticaModuleGenerator::class)->missingByModule($pratica, $client, $modules);

        return [
            CheckboxList::make('moduli')
                ->label('Moduli')
                ->options($modules->pluck('name', 'id')->all())
                ->descriptions($modules->mapWithKeys(fn (PdfModule $module) => [
                    $module->getKey() => $missing[$module->getKey()] === []
                        ? 'Dati completi'
                        : 'Dati mancanti: '.collect($missing[$module->getKey()])->map->getLabel()->implode(', '),
                ])->all())
                ->default(app(ModuleSuggester::class)->suggest($pratica, $client)->pluck('id')->all())
                ->required()
                ->columns(1),
        ];
    }

    /**
     * @param  array<int, int|string>  $moduleIds
     */
    private function generate(Pratica $pratica, array $moduleIds): void
    {
        $client = $this->clientFor($pratica);

        if ($client === null) {
            Notification::make()
                ->title('Cliente non trovato')
                ->body('Nessun cliente corrisponde al codice fiscale della pratica.')
                ->danger()
                ->send();

            return;
        }

        $modules = PdfModule::query()->active()->whereKey($moduleIds)->with('fields')->orderBy('name')->get();

        try {
            $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, $modules, auth()->user());
        } catch (PdfFormException $exception) {
            report($exception);

            Notification::make()
                ->title('Compilazione non disponibile')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($documents->count() === 1 ? 'Modulo generato' : $documents->count().' moduli generati')
            ->body('I PDF sono stati salvati tra i documenti della pratica.')
            ->success()
            ->persistent()
            ->actions($documents->map(fn (Document $document) => Action::make('scarica_'.$document->getKey())
                ->label($document->name)
                ->button()
                ->url(route('documents.download', $document), shouldOpenInNewTab: true))->all())
            ->send();
    }
}
```

- [ ] **Step 4: Registrare l'azione su `EditPratica`**

In [app/Filament/Resources/Praticas/Pages/EditPratica.php](../../../app/Filament/Resources/Praticas/Pages/EditPratica.php): aggiungere `use App\Filament\Actions\GeneraModuliPraticaAction;` agli import e, nell'array di `getHeaderActions()`, inserire `GeneraModuliPraticaAction::make(),` immediatamente prima di `DeleteAction::make(),`. Non toccare altro nel file (l'azione `cambia_banca` usa `DB` e `Notification` senza import: è un problema preesistente, da segnalare all'utente e non da correggere qui).

- [ ] **Step 5: Eseguire i test**

Run: `php artisan test --compact tests/Feature/GeneraModuliPraticaActionTest.php`
Expected: tutti PASS. Se il mount di `EditPratica` fallisce per ragioni estranee all'azione (relation manager della pagina), leggere l'errore: il test deve montare la pagina reale; non sostituirlo con un test dell'azione isolata.

- [ ] **Step 6: Pint e commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Filament/Actions/GeneraModuliPraticaAction.php app/Filament/Resources/Praticas/Pages/EditPratica.php tests/Feature/GeneraModuliPraticaActionTest.php
git commit -m "feat: azione Genera moduli sul dettaglio pratica"
```

---

### Task 9: Chiusura — DEPLOY.md, verifica end-to-end e regressione

**Files:**
- Modify: `DEPLOY.md`

**Interfaces:**
- Consumes: tutto il lavoro dei task 1-8.

- [ ] **Step 1: Aggiornare [DEPLOY.md](../../../DEPLOY.md)**

Sostituire le sezioni "3. Migration" e "5. Variabili d'ambiente" e aggiungere la sezione dei passi post-deploy:

```markdown
## 3. Migration

Ordine (tutte idempotenti, `php artisan migrate --force`):

| Migration | Connessione | Cosa fa |
|---|---|---|
| `2026_10_06_100000_add_iban_and_employer_to_clients_table` | `mysql_proforma` | aggiunge `iban` ed `employer_id` (self-FK) a `proforma.clients` |
| `2026_10_06_100100_create_pdf_modules_table` | `mysql` | tabella `pdf_modules` |
| `2026_10_06_100200_create_pdf_module_fields_table` | `mysql` | tabella `pdf_module_fields` |

Attenzione: la prima tocca il database `proforma` (colonna `iban`, FK `employer_id`): eseguirla con l'utente DB che ha `ALTER` su `proforma.clients`.

## 5. Variabili d'ambiente

- `PDFTK_BINARY` (opzionale, default `pdftk`): percorso del binario se non e' nel PATH di PHP-FPM.

## 6. Dopo il deploy (una tantum)

```bash
php artisan db:seed --class=PdfModuleSeeder --force   # registra i 13 moduli con prodotti/ambito di default
php artisan modules:sync-fields --diagnostic          # legge i campi dei PDF e genera i PDF diagnostici
php artisan permissions:sync-resources                # registra la risorsa "Moduli PDF"
```

Poi, da Filament > Sistema > Moduli PDF, mappare i campi di ciascun modulo (aprire il PDF diagnostico per vedere dove sta ogni campo). Il comando `modules:sync-fields` va rieseguito quando si sostituisce un PDF con una nuova versione: non tocca le mappature esistenti.

I PDF sono in `storage/app/public/` (ignorato da git): copiare `module/` sul server insieme al codice.
```

- [ ] **Step 2: Eseguire tutti i test nuovi e quelli adiacenti**

Run:
```bash
php artisan test --compact tests/Feature/ClientEmployerIbanTest.php tests/Feature/PdfModuleModelTest.php tests/Feature/PdfModuleSeederTest.php tests/Unit/ModuleDataResolverTest.php tests/Feature/ModuleDataResolverClientLookupTest.php tests/Feature/PdfFormFillerTest.php tests/Feature/PdfFieldSynchronizerTest.php tests/Feature/ModuleSuggesterTest.php tests/Feature/PraticaModuleGeneratorTest.php tests/Feature/PdfModuleResourceTest.php tests/Feature/GeneraModuliPraticaActionTest.php tests/Feature/FilamentResourceDiscoveryTest.php tests/Unit/RelationManagerWiringTest.php
```
Expected: tutti PASS, nessuno skip (pdftk installato). Poi `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 3: Verifica end-to-end manuale su dati reali (con l'utente)**

1. `php artisan modules:sync-fields --diagnostic` e aprire `storage/app/public/module-diagnostica/…Prestiti Personali….pdf`: i campi mostrano i propri nomi.
2. In Filament > Moduli PDF > "Fascicolo … Prestiti Personali": mappare 3-4 campi (es. nominativo, codice fiscale, importo con formato "Importo", data odierna con formato "Data").
3. Aprire una pratica di tipo Prestito con cliente esistente → "Genera moduli" → verificare anteprima dati mancanti, generazione, link di download, comparsa tra i Documenti della pratica e riga nel log attività.

- [ ] **Step 4: Chiedere all'utente se eseguire l'intera suite**

Regola di progetto: a test della feature verdi, chiedere se lanciare `php artisan test --compact` completo. Non lanciarla senza risposta.

- [ ] **Step 5: Commit**

```bash
git add DEPLOY.md
git commit -m "docs: note di deploy per la compilazione dei moduli PDF"
```

---

## Self-Review

**Copertura della spec**

| Requisito della spec | Task |
|---|---|
| Migration `iban`, `employer_id` self-FK su `clients` (`mysql_proforma`) | 1 |
| Relazioni `employer()`/`employees()`, fix `clientPratiches()` | 1 |
| Campi nel form Filament cliente | 1 |
| `pdf_modules` / `pdf_module_fields`, model `mysql`, factory, seeder | 2 |
| Whitelist `ModuleSourceKey` + formattatori | 2 (enum), 3 (uso) |
| `ModuleDataResolver` (cliente via CF, sede, documento, datore) | 3 |
| `PdfFormFiller` (`fill_form`, flatten, `PDFTK_BINARY`) | 4 |
| `ModuleSuggester` | 6 |
| `modules:sync-fields` + `--diagnostic` | 5 |
| Risorsa Filament di mappatura + "Scarica PDF diagnostico" | 7 |
| Azione "Genera moduli" (suggeriti, anteprima mancanti, Document, activity log) | 6 (logica), 8 (UI) |
| Autorizzazione tramite gate esistente | 7 (`HasPlanAccess`), 8 (azione su `EditPratica` già gated da `PraticaResource`) |
| Errori: cliente non trovato, dato mancante, pdftk assente, modulo con campi diversi | 3, 4, 5, 6, 8 |
| IBAN in chiaro, mai nel log | 1, 6 (test sul contenuto del log) |
| Test PHPUnit, skip senza pdftk | tutti |
| DEPLOY.md | 9 |

**Scostamenti dalla spec, dichiarati**
- Il soggetto dell'activity log è il **Client**, non la Pratica: `activity_log.subject_id` è `bigint` e `pratiches.id` è un UUID. La pratica è in `properties.pratica_id`.
- Download: notifica con link a `documents.download` per ogni documento, invece di un download automatico/ZIP (un'azione Filament non garantisce di restituire uno stream; il link usa la rotta autenticata già esistente). Il PDF diagnostico è salvato sul disco `public` (contiene solo nomi di campo).
- Aggiunta la chiave `client.nominativo` ("Cognome Nome"), non elencata nella spec ma necessaria per i campi "cliente" dei moduli; la regola "una riga nell'enum + il suo ramo nel resolver" della spec la contempla.
- Sede: fallback alla sede più recente non dismessa se nessuna è marcata `is_main_office` (Review Focus 1); le sedi dismesse sono sempre escluse.

**Scansione placeholder:** nessun TBD/TODO. Due note condizionali esplicite (Task 3 Step 5 ordinamento con array; Task 7 `searchableOptions`; Task 5 Step 5 file non-PDF) indicano l'alternativa concreta da applicare se il comportamento reale differisce.

**Coerenza dei tipi:** `ResolvedModuleData::get/has/isTruthy/missingAmong`, `ModuleDataResolver::findClient/resolve`, `PdfFormFiller::open/buildFieldValues/fill/fillDiagnostic/storeDiagnostic`, `PdfFieldSynchronizer::discoverFiles/sync/moduleNameFromPath`, `PraticaModuleGenerator::missingByModule/generate`, `PdfModule::appliesTo/scopeActive/fields`, `PdfModuleField::isCheckbox/referencedKeys` sono usati con le stesse firme in tutti i task. Le chiavi `Key::*` negli esempi coincidono con i case dell'enum del Task 2.

**Problema preesistente da segnalare all'utente (non incluso nel piano):** `EditPratica::getHeaderActions()` usa `DB::transaction` e `Notification::make()` senza gli `use` relativi; l'azione "Cambia Banca" va in errore al click.

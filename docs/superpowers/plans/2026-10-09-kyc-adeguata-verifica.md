# KYC Adeguata Verifica Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Questionario KYC versionato per cliente (con titolari effettivi per le società), mostrato in scheda cliente, lista clienti e pratica come indicatore informativo.

**Architecture:** Due nuove tabelle sulla connessione di default (`mysql`), modelli Eloquent + enum, un service puro che suggerisce i titolari effettivi dalle cariche sociali, un RelationManager Filament sul cliente. `Client` (connessione `mysql_proforma`) espone `kycCoverage()`. Nessun blocco delle pratiche.

**Tech Stack:** Laravel 13, Filament v5, Livewire v4, PHPUnit 12, MySQL (test su `unicooam_test`).

**Spec:** `docs/superpowers/specs/2026-10-09-kyc-adeguata-verifica-design.md`

## Global Constraints

- Soglia titolare effettivo: **quota strettamente > 25%** (art. 20 D.Lgs. 231/2007, "25% più uno"). Ha la precedenza sul "≥ 25%" della spec: Task 1 corregge la spec.
- `Client` e `Pratica` sono su `mysql_proforma`: niente FK native verso `clients`/`pratiches`, niente subquery cross-connection (usare `pluck()` + `whereIn`).
- `client_id` = `unsignedBigInteger` (clients.id è bigint unsigned); `pratica_id` = `string(64)` (id pratica è uuid varchar); `client_mandate_id` = `foreignId` verso `client_mandates` (stessa connessione, bigint unsigned).
- Enum: `string` backed, `implements HasLabel`, stile di `app/Enums/AmlReportStatus.php`.
- Test: PHPUnit, `LazilyRefreshDatabase`, `protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];` (vedi `tests/Feature/ClientEmployerIbanTest.php`).
- Dopo ogni modifica PHP: `vendor/bin/pint --dirty --format agent`.
- Nessuna nuova dipendenza. Solo `php artisan make:*` con `--no-interaction`.
- Testi UI in italiano.

## Review Focus

- Cliente con più questionari approvati: conta l'ultimo; uno bozza più recente non annulla l'approvato precedente (Task 2).
- `next_review_at` nullo su approvato = non scade (Task 2).
- Società senza nessuna quota > 25% e senza rappresentante legale: suggerimento vuoto, non errore (Task 3).
- Carica sociale con `data_fine_ruolo` passata: ignorata nel suggerimento (Task 3).
- Quota esattamente 25.00: NON titolare (Task 3).
- Approvazione di una società con titolare non verificato o senza titolari: rifiutata con elenco dei requisiti mancanti (Task 4).
- Persona fisica: nessun requisito sui titolari (Task 4).

---

### Task 1: Enum, migrazioni, modelli, factory

**Files:**
- Create: `app/Enums/KycRiskLevel.php`, `app/Enums/KycStatus.php`, `app/Enums/KycControlType.php`, `app/Enums/KycCoverage.php`
- Create (via artisan): `app/Models/KycQuestionnaire.php`, `app/Models/KycBeneficialOwner.php`, migrazioni, factory
- Modify: `docs/superpowers/specs/2026-10-09-kyc-adeguata-verifica-design.md` (soglia)
- Test: `tests/Feature/KycModelTest.php`

**Interfaces:**
- Produces:
  - `KycRiskLevel::{LOW='low',MEDIUM='medium',HIGH='high'}` con `reviewMonths(): int` (6/12/36 → HIGH 6, MEDIUM 12, LOW 36).
  - `KycStatus::{DRAFT='draft',COMPLETE='complete',APPROVED='approved'}`.
  - `KycControlType::{SHARES='shares',CONTROL='control',RESIDUAL='residual'}`.
  - `KycCoverage::{MISSING='missing',EXPIRED='expired',COMPLETE='complete'}` con `getColor(): string` (danger/warning/success).
  - `KycQuestionnaire` (fillable: `client_id, client_mandate_id, pratica_id, purpose_of_relationship, funds_origin, occupation, income_source, is_remote_interaction, is_pep, high_risk_countries, risk_level, status, compiled_at, verified_by, verified_at, next_review_at, notes`), relazioni `client(): BelongsTo`, `beneficialOwners(): HasMany`; factory `KycQuestionnaire::factory()` con stati `approved()`, `draft()`.
  - `KycBeneficialOwner` (fillable: `kyc_questionnaire_id, client_id, shares_percentage, control_type, declaration_signed_at, document_id, is_verified`), relazioni `questionnaire()`, `person(): BelongsTo` (Client).

- [ ] **Step 1: Correggi la soglia nella spec**

In `docs/superpowers/specs/2026-10-09-kyc-adeguata-verifica-design.md` sostituisci "quota ≥ 25%" con "quota > 25% (25% più uno, art. 20 D.Lgs. 231/2007)".

- [ ] **Step 2: Scrivi il test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Enums\KycControlType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KycModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    public function test_questionnaire_casts_and_owners_relation(): void
    {
        $questionnaire = KycQuestionnaire::factory()->create([
            'client_id' => 1,
            'risk_level' => KycRiskLevel::HIGH,
        ]);
        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id,
            'client_id' => 2,
            'shares_percentage' => 40,
            'control_type' => KycControlType::SHARES,
        ]);

        $questionnaire->refresh();

        $this->assertSame(KycRiskLevel::HIGH, $questionnaire->risk_level);
        $this->assertSame(KycStatus::DRAFT, $questionnaire->status);
        $this->assertCount(1, $questionnaire->beneficialOwners);
        $this->assertSame(KycControlType::SHARES, $questionnaire->beneficialOwners->first()->control_type);
        $this->assertFalse($questionnaire->beneficialOwners->first()->is_verified);
    }

    public function test_review_months_per_risk_level(): void
    {
        $this->assertSame(6, KycRiskLevel::HIGH->reviewMonths());
        $this->assertSame(12, KycRiskLevel::MEDIUM->reviewMonths());
        $this->assertSame(36, KycRiskLevel::LOW->reviewMonths());
    }
}
```

- [ ] **Step 3: Esegui e verifica che fallisca**

Run: `php artisan test --compact tests/Feature/KycModelTest.php`
Expected: FAIL (classi inesistenti).

- [ ] **Step 4: Genera i file**

```bash
php artisan make:enum KycRiskLevel --no-interaction   # se make:enum non esiste: crea il file a mano
php artisan make:model KycQuestionnaire -mf --no-interaction
php artisan make:model KycBeneficialOwner -m --no-interaction
```

Enum (esempio `KycRiskLevel`; le altre seguono lo stesso schema con label italiane: Bozza/Completo/Approvato; Quote/Controllo/Residuale (rappresentante legale); Mancante/Scaduto/Completo):

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycRiskLevel: string implements HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Basso',
            self::MEDIUM => 'Medio',
            self::HIGH => 'Alto',
        };
    }

    public function reviewMonths(): int
    {
        return match ($this) {
            self::LOW => 36,
            self::MEDIUM => 12,
            self::HIGH => 6,
        };
    }
}
```

`KycCoverage::getColor()`: MISSING→`danger`, EXPIRED→`warning`, COMPLETE→`success`.

Migrazione `kyc_questionnaires` (rinomina il file generato in `2026_10_09_100000_create_kyc_questionnaires_table.php`; usa `if (Schema::hasTable(...)) { return; }` come le altre):

```php
Schema::create('kyc_questionnaires', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('client_id')->index()->comment('mysql_proforma.clients.id');
    $table->foreignId('client_mandate_id')->nullable()->constrained('client_mandates')->nullOnDelete();
    $table->string('pratica_id', 64)->nullable()->comment('mysql_proforma.pratiches.id');
    $table->text('purpose_of_relationship')->nullable();
    $table->text('funds_origin')->nullable();
    $table->string('occupation')->nullable();
    $table->string('income_source')->nullable();
    $table->boolean('is_remote_interaction')->default(false);
    $table->boolean('is_pep')->default(false);
    $table->text('high_risk_countries')->nullable();
    $table->string('risk_level')->nullable();
    $table->string('status')->default('draft')->index();
    $table->dateTime('compiled_at')->nullable();
    $table->string('verified_by')->nullable();
    $table->dateTime('verified_at')->nullable();
    $table->date('next_review_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

Migrazione `kyc_beneficial_owners` (`2026_10_09_100100_...`):

```php
Schema::create('kyc_beneficial_owners', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kyc_questionnaire_id')->constrained('kyc_questionnaires')->cascadeOnDelete();
    $table->unsignedBigInteger('client_id')->index()->comment('persona fisica, mysql_proforma.clients.id');
    $table->decimal('shares_percentage', 5, 2)->nullable();
    $table->string('control_type');
    $table->dateTime('declaration_signed_at')->nullable();
    $table->char('document_id', 36)->nullable()->comment('documents.id');
    $table->boolean('is_verified')->default(false);
    $table->timestamps();
});
```

Modelli: `$fillable` come in Interfaces; casts (`risk_level` => `KycRiskLevel::class`, `status` => `KycStatus::class`, `control_type` => `KycControlType::class`, booleani, `compiled_at`/`verified_at` datetime, `next_review_at` date, `shares_percentage` => `decimal:2`, `declaration_signed_at` datetime). `KycQuestionnaire::client()` = `belongsTo(Client::class)`; `beneficialOwners()` = `hasMany(KycBeneficialOwner::class)`. `KycBeneficialOwner::person()` = `belongsTo(Client::class, 'client_id')`.

Factory: `client_id => 1`, `status => KycStatus::DRAFT`, campi testuali con `fake()`; stati `draft()` e `approved()` (status APPROVED, `risk_level` MEDIUM, `verified_at` now, `verified_by` 'Tester', `next_review_at` now()->addYear()).

- [ ] **Step 5: Esegui il test**

Run: `php artisan test --compact tests/Feature/KycModelTest.php`
Expected: PASS

- [ ] **Step 6: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app database tests docs
git commit -m "feat(kyc): tabelle, modelli ed enum del questionario KYC

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Copertura KYC del cliente

**Files:**
- Modify: `app/Models/KycQuestionnaire.php`, `app/Models/Client.php`
- Test: `tests/Feature/KycCoverageTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - `KycQuestionnaire::scopeApproved(Builder): Builder`
  - `KycQuestionnaire::scopeLatestFirst(Builder): Builder` (ordina `verified_at` desc, `id` desc)
  - `KycQuestionnaire::coverageByClient(): Collection` → mappa `client_id => KycCoverage` solo per clienti con almeno un approvato
  - `Client::kycQuestionnaires(): HasMany`, `Client::currentKyc(): ?KycQuestionnaire`, `Client::kycCoverage(): KycCoverage`

- [ ] **Step 1: Test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Enums\KycCoverage;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycCoverageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    public function test_client_without_questionnaires_is_missing(): void
    {
        $this->assertSame(KycCoverage::MISSING, $this->makeClient()->kycCoverage());
    }

    public function test_only_draft_is_still_missing(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->draft()->create(['client_id' => $client->id]);

        $this->assertSame(KycCoverage::MISSING, $client->kycCoverage());
    }

    public function test_approved_not_expired_is_complete_and_null_review_never_expires(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id, 'next_review_at' => null]);

        $this->assertSame(KycCoverage::COMPLETE, $client->kycCoverage());
    }

    public function test_approved_with_past_review_date_is_expired(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->approved()->create([
            'client_id' => $client->id, 'next_review_at' => now()->subDay(),
        ]);

        $this->assertSame(KycCoverage::EXPIRED, $client->kycCoverage());
    }

    public function test_latest_approved_wins_and_newer_draft_does_not_cancel_it(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->approved()->create([
            'client_id' => $client->id, 'next_review_at' => now()->subYear(), 'verified_at' => now()->subYears(2),
        ]);
        KycQuestionnaire::factory()->approved()->create([
            'client_id' => $client->id, 'next_review_at' => now()->addYear(), 'verified_at' => now()->subDay(),
        ]);
        KycQuestionnaire::factory()->draft()->create(['client_id' => $client->id]);

        $this->assertSame(KycCoverage::COMPLETE, $client->kycCoverage());
    }

    public function test_coverage_by_client_maps_only_clients_with_an_approved_questionnaire(): void
    {
        $complete = $this->makeClient();
        $expired = $this->makeClient();
        $draftOnly = $this->makeClient();
        KycQuestionnaire::factory()->approved()->create(['client_id' => $complete->id]);
        KycQuestionnaire::factory()->approved()->create(['client_id' => $expired->id, 'next_review_at' => now()->subDay()]);
        KycQuestionnaire::factory()->draft()->create(['client_id' => $draftOnly->id]);

        $map = KycQuestionnaire::coverageByClient();

        $this->assertSame(KycCoverage::COMPLETE, $map[$complete->id]);
        $this->assertSame(KycCoverage::EXPIRED, $map[$expired->id]);
        $this->assertArrayNotHasKey($draftOnly->id, $map->all());
    }
}
```

- [ ] **Step 2: Esegui, atteso FAIL** — `php artisan test --compact tests/Feature/KycCoverageTest.php`

- [ ] **Step 3: Implementa**

In `KycQuestionnaire`:

```php
public function scopeApproved(Builder $query): Builder
{
    return $query->where('status', KycStatus::APPROVED);
}

public function scopeLatestFirst(Builder $query): Builder
{
    return $query->orderByDesc('verified_at')->orderByDesc('id');
}

public function coverage(): KycCoverage
{
    return $this->next_review_at !== null && $this->next_review_at->lt(today())
        ? KycCoverage::EXPIRED
        : KycCoverage::COMPLETE;
}

/**
 * @return Collection<int, KycCoverage>
 */
public static function coverageByClient(): Collection
{
    return static::approved()->latestFirst()->get()
        ->unique('client_id')
        ->mapWithKeys(fn (self $q) => [$q->client_id => $q->coverage()]);
}
```

In `Client`:

```php
public function kycQuestionnaires(): HasMany
{
    return $this->hasMany(KycQuestionnaire::class);
}

public function currentKyc(): ?KycQuestionnaire
{
    return $this->kycQuestionnaires()->approved()->latestFirst()->first();
}

public function kycCoverage(): KycCoverage
{
    return $this->currentKyc()?->coverage() ?? KycCoverage::MISSING;
}
```

(Aggiungi gli `use` mancanti.)

- [ ] **Step 4: Esegui, atteso PASS**

- [ ] **Step 5: Pint e commit** — `git commit -m "feat(kyc): copertura KYC del cliente (mancante/scaduto/completo)"` (con trailer Co-Authored-By come sopra).

---

### Task 3: Suggerimento titolari effettivi

**Files:**
- Create: `app/Services/Kyc/BeneficialOwnerSuggester.php`
- Test: `tests/Unit/BeneficialOwnerSuggesterTest.php`

**Interfaces:**
- Produces: `BeneficialOwnerSuggester::THRESHOLD = 25.0`; `suggest(Collection $relations, ?int $legalRepresentativeId): array` → lista di `array{client_id: int, shares_percentage: ?float, control_type: KycControlType}`. `$relations` = collection di `ClientRelation` (anche non persistiti). Puro, nessun accesso al DB.

- [ ] **Step 1: Test che fallisce** (estende `PHPUnit\Framework\TestCase`: nessun DB)

```php
<?php

namespace Tests\Unit;

use App\Enums\KycControlType;
use App\Models\ClientRelation;
use App\Services\Kyc\BeneficialOwnerSuggester;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BeneficialOwnerSuggesterTest extends TestCase
{
    private function relation(int $clientId, ?float $shares, ?string $endedAt = null): ClientRelation
    {
        return new ClientRelation([
            'client_id' => $clientId,
            'shares_percentage' => $shares,
            'data_fine_ruolo' => $endedAt,
        ]);
    }

    public function test_only_shares_strictly_above_threshold_are_suggested(): void
    {
        $result = (new BeneficialOwnerSuggester)->suggest(new Collection([
            $this->relation(1, 60.0),
            $this->relation(2, 25.0),
            $this->relation(3, 25.01),
            $this->relation(4, null),
        ]), legalRepresentativeId: 9);

        $this->assertSame([1, 3], array_column($result, 'client_id'));
        $this->assertSame(KycControlType::SHARES, $result[0]['control_type']);
    }

    public function test_ended_roles_are_ignored(): void
    {
        $result = (new BeneficialOwnerSuggester)->suggest(new Collection([
            $this->relation(1, 80.0, '2020-01-01'),
        ]), legalRepresentativeId: 9);

        $this->assertSame([9], array_column($result, 'client_id'));
    }

    public function test_falls_back_to_legal_representative_as_residual(): void
    {
        $result = (new BeneficialOwnerSuggester)->suggest(new Collection([$this->relation(2, 10.0)]), 9);

        $this->assertCount(1, $result);
        $this->assertSame(9, $result[0]['client_id']);
        $this->assertNull($result[0]['shares_percentage']);
        $this->assertSame(KycControlType::RESIDUAL, $result[0]['control_type']);
    }

    public function test_returns_empty_when_nobody_qualifies_and_no_legal_representative(): void
    {
        $this->assertSame([], (new BeneficialOwnerSuggester)->suggest(new Collection, null));
    }
}
```

- [ ] **Step 2: Esegui, atteso FAIL** — `php artisan test --compact tests/Unit/BeneficialOwnerSuggesterTest.php`

- [ ] **Step 3: Implementa**

```php
<?php

namespace App\Services\Kyc;

use App\Enums\KycControlType;
use App\Models\ClientRelation;
use Illuminate\Support\Collection;

class BeneficialOwnerSuggester
{
    public const THRESHOLD = 25.0;

    /**
     * @param  Collection<int, ClientRelation>  $relations
     * @return array<int, array{client_id: int, shares_percentage: float|null, control_type: KycControlType}>
     */
    public function suggest(Collection $relations, ?int $legalRepresentativeId): array
    {
        $owners = $relations
            ->filter(fn (ClientRelation $r) => $r->data_fine_ruolo === null || $r->data_fine_ruolo->isFuture())
            ->filter(fn (ClientRelation $r) => (float) $r->shares_percentage > self::THRESHOLD)
            ->map(fn (ClientRelation $r) => [
                'client_id' => (int) $r->client_id,
                'shares_percentage' => (float) $r->shares_percentage,
                'control_type' => KycControlType::SHARES,
            ])
            ->values()
            ->all();

        if ($owners === [] && $legalRepresentativeId !== null) {
            return [[
                'client_id' => $legalRepresentativeId,
                'shares_percentage' => null,
                'control_type' => KycControlType::RESIDUAL,
            ]];
        }

        return $owners;
    }
}
```

- [ ] **Step 4: Esegui, atteso PASS**

- [ ] **Step 5: Pint e commit** — `feat(kyc): suggerimento titolari effettivi dalle cariche sociali`.

---

### Task 4: Completezza e approvazione

**Files:**
- Modify: `app/Models/KycQuestionnaire.php`
- Test: `tests/Feature/KycApprovalTest.php`

**Interfaces:**
- Consumes: Task 1, 2.
- Produces: `KycQuestionnaire::missingRequirements(): array` (lista di stringhe italiane, vuota = completo); `KycQuestionnaire::approve(string $verifiedBy): void` (lancia `\DomainException` col testo dei requisiti mancanti se incompleto; altrimenti imposta `status` APPROVED, `verified_by`, `verified_at = now()`, e se `next_review_at` è nullo lo calcola da `risk_level->reviewMonths()`).

Requisiti: sempre `purpose_of_relationship`, `funds_origin`, `occupation`, `risk_level`. Se `client->is_person` è falso: almeno un titolare effettivo e tutti `is_verified`.

- [ ] **Step 1: Test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Enums\KycControlType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\Client;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(bool $isPerson): Client
    {
        return Client::create([
            'name' => 'Acme', 'is_person' => $isPerson, 'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    private function filled(Client $client, array $overrides = []): KycQuestionnaire
    {
        return KycQuestionnaire::factory()->draft()->create(array_merge([
            'client_id' => $client->id,
            'purpose_of_relationship' => 'Mediazione creditizia',
            'funds_origin' => 'Stipendio',
            'occupation' => 'Impiegato',
            'risk_level' => KycRiskLevel::MEDIUM,
        ], $overrides));
    }

    public function test_person_with_required_fields_can_be_approved_and_gets_default_review_date(): void
    {
        $questionnaire = $this->filled($this->makeClient(true));

        $questionnaire->approve('Laura Bianchi');
        $questionnaire->refresh();

        $this->assertSame(KycStatus::APPROVED, $questionnaire->status);
        $this->assertSame('Laura Bianchi', $questionnaire->verified_by);
        $this->assertTrue($questionnaire->next_review_at->isSameDay(now()->addMonths(12)));
    }

    public function test_explicit_next_review_date_is_kept(): void
    {
        $questionnaire = $this->filled($this->makeClient(true), ['next_review_at' => '2030-01-01']);

        $questionnaire->approve('Laura Bianchi');

        $this->assertSame('2030-01-01', $questionnaire->fresh()->next_review_at->toDateString());
    }

    public function test_missing_required_fields_block_approval(): void
    {
        $questionnaire = $this->filled($this->makeClient(true), ['funds_origin' => null, 'risk_level' => null]);

        $this->assertCount(2, $questionnaire->missingRequirements());
        $this->expectException(\DomainException::class);
        $questionnaire->approve('X');
    }

    public function test_company_needs_at_least_one_verified_beneficial_owner(): void
    {
        $questionnaire = $this->filled($this->makeClient(false));
        $this->assertNotEmpty($questionnaire->missingRequirements());

        $owner = KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id, 'client_id' => 5,
            'shares_percentage' => 50, 'control_type' => KycControlType::SHARES, 'is_verified' => false,
        ]);
        $this->assertNotEmpty($questionnaire->fresh()->missingRequirements());

        $owner->update(['is_verified' => true]);
        $this->assertSame([], $questionnaire->fresh()->missingRequirements());
    }

    public function test_person_is_not_asked_for_beneficial_owners(): void
    {
        $this->assertSame([], $this->filled($this->makeClient(true))->missingRequirements());
    }
}
```

- [ ] **Step 2: Esegui, atteso FAIL**

- [ ] **Step 3: Implementa** in `KycQuestionnaire`

```php
/**
 * @return array<int, string>
 */
public function missingRequirements(): array
{
    $missing = [];

    foreach ([
        'purpose_of_relationship' => 'Scopo e natura del rapporto',
        'funds_origin' => 'Origine dei fondi',
        'occupation' => 'Professione / attività',
        'risk_level' => 'Livello di rischio',
    ] as $field => $label) {
        if (blank($this->{$field})) {
            $missing[] = $label;
        }
    }

    if ($this->client && ! $this->client->is_person) {
        $owners = $this->beneficialOwners;

        if ($owners->isEmpty()) {
            $missing[] = 'Almeno un titolare effettivo';
        } elseif ($owners->contains(fn (KycBeneficialOwner $o) => ! $o->is_verified)) {
            $missing[] = 'Verifica di tutti i titolari effettivi';
        }
    }

    return $missing;
}

public function approve(string $verifiedBy): void
{
    $missing = $this->missingRequirements();

    if ($missing !== []) {
        throw new \DomainException('Requisiti mancanti: '.implode(', ', $missing));
    }

    $this->update([
        'status' => KycStatus::APPROVED,
        'verified_by' => $verifiedBy,
        'verified_at' => now(),
        'next_review_at' => $this->next_review_at ?? now()->addMonths($this->risk_level->reviewMonths()),
    ]);
}
```

- [ ] **Step 4: Esegui, atteso PASS**

- [ ] **Step 5: Pint e commit** — `feat(kyc): requisiti di completezza e approvazione`.

---

### Task 5: RelationManager sul cliente

**Files:**
- Create: `app/Filament/Resources/Clients/RelationManagers/KycQuestionnairesRelationManager.php`
- Modify: `app/Filament/Resources/Clients/ClientResource.php:55-60` (registra l'RM al posto del commento `ChecklistsRelationManager`)
- Test: `tests/Feature/KycRelationManagerTest.php`

**Interfaces:**
- Consumes: Task 1-4, `BeneficialOwnerSuggester`.
- Produces: `KycQuestionnairesRelationManager` (relationship `kycQuestionnaires`), azioni di tabella `approve` (visibile solo se non approvato).

Note: NON usare `HasRelationPlanAccess` (non esiste una feature key per il KYC); `canViewForRecord` restituisce `true`. Verificare le API v5 con `search-docs` (Boost) o https://filamentphp.com/docs prima di scrivere: `Schemas\Components\Actions`, `Repeater::relationship()`, `TestAction::make(...)->table($record)`.

- [ ] **Step 1: Test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\KycQuestionnairesRelationManager;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KycRelationManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function manager(Client $client)
    {
        return Livewire::test(KycQuestionnairesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => EditClient::class,
        ]);
    }

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_lists_the_client_questionnaires(): void
    {
        $client = $this->makeClient();
        $records = KycQuestionnaire::factory()->count(2)->draft()->create(['client_id' => $client->id]);

        $this->manager($client)->assertCanSeeTableRecords($records);
    }

    public function test_approve_action_approves_a_complete_questionnaire(): void
    {
        $client = $this->makeClient();
        $record = KycQuestionnaire::factory()->draft()->create([
            'client_id' => $client->id,
            'purpose_of_relationship' => 'x', 'funds_origin' => 'x', 'occupation' => 'x',
            'risk_level' => 'low',
        ]);

        $this->manager($client)->callAction(TestAction::make('approve')->table($record))->assertNotified();

        $this->assertSame(KycStatus::APPROVED, $record->fresh()->status);
    }

    public function test_approve_action_reports_missing_requirements_instead_of_approving(): void
    {
        $client = $this->makeClient();
        $record = KycQuestionnaire::factory()->draft()->create([
            'client_id' => $client->id, 'funds_origin' => null,
        ]);

        $this->manager($client)->callAction(TestAction::make('approve')->table($record))->assertNotified();

        $this->assertSame(KycStatus::DRAFT, $record->fresh()->status);
    }

    public function test_creating_a_questionnaire_keeps_the_previous_one(): void
    {
        $client = $this->makeClient();
        $old = KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id]);

        $this->manager($client)->callAction(TestAction::make('create')->table(), [
            'purpose_of_relationship' => 'Nuovo', 'funds_origin' => 'Nuovo', 'occupation' => 'Nuovo',
            'risk_level' => 'high',
        ])->assertHasNoFormErrors();

        $this->assertSame(2, $client->kycQuestionnaires()->count());
        $this->assertSame(KycStatus::APPROVED, $old->fresh()->status);
    }
}
```

- [ ] **Step 2: Esegui, atteso FAIL**

- [ ] **Step 3: Implementa l'RM**

Struttura (segui lo stile di `ClientRelationsRelationManager`; namespace Filament v5 come da CLAUDE.md):

- `protected static string $relationship = 'kycQuestionnaires'; protected static ?string $title = 'KYC / Adeguata verifica';`
- `form(Schema $schema)`: `Section` "Questionario" (Textarea `purpose_of_relationship`, `funds_origin`; TextInput `occupation`, `income_source`; Toggle `is_pep`, `is_remote_interaction`; Textarea `high_risk_countries`; Select `risk_level` con `->options(KycRiskLevel::class)`, `->live()` e `afterStateUpdated` che imposta `next_review_at` a `now()->addMonths($level->reviewMonths())`; DatePicker `next_review_at`, `compiled_at` (default now); Select `client_mandate_id` con i mandati del cliente; Textarea `notes`). `Section` "Titolari effettivi" `->visible(fn () => ! $this->getOwnerRecord()->is_person)` con: `Actions` > `Action::make('prefill_owners')->label('Precompila da cariche sociali')` che chiama `BeneficialOwnerSuggester::suggest($client->companyRelations, $client->legal_representative_id)` e fa `$set('beneficialOwners', [...])`; `Repeater::make('beneficialOwners')->relationship()` con Select `client_id` (persone fisiche, ricerca per nome/CF, `getOptionLabelUsing`), TextInput `shares_percentage`, Select `control_type` (`KycControlType`), DateTimePicker `declaration_signed_at`, Toggle `is_verified`.
- `table(Table $table)`: colonne `compiled_at` (data), `risk_level` badge, `status` badge, `verified_by`, `verified_at`, `next_review_at`; `headerActions` `CreateAction::make()->label('Nuova compilazione')`; record actions `EditAction`, e `Action::make('approve')->label('Approva')->icon('heroicon-o-check-badge')->visible(fn (KycQuestionnaire $r) => $r->status !== KycStatus::APPROVED)->requiresConfirmation()->action(...)` che chiama `$record->approve(auth()->user()->name)` in try/catch su `\DomainException` → `Notification::make()->danger()->title('KYC incompleto')->body($e->getMessage())->send()`, altrimenti `->success()->title('KYC approvato')`. Nessun `DeleteAction` sui record approvati (storico): `->visible(fn ($r) => $r->status !== KycStatus::APPROVED)` su `DeleteAction`.
- In `mutateFormDataUsing` del `CreateAction`: `$data['status'] = KycStatus::DRAFT; $data['compiled_at'] ??= now();`.

In `ClientResource::getRelations()` sostituisci la riga commentata `// ChecklistsRelationManager::class,` con `KycQuestionnairesRelationManager::class,` (+ `use`).

- [ ] **Step 4: Esegui, atteso PASS** — `php artisan test --compact tests/Feature/KycRelationManagerTest.php`

- [ ] **Step 5: Test del prefill** (aggiungi a `KycApprovalTest` o nuovo file): verifica che l'azione `prefill_owners` popoli il repeater usando una `Collection` di `ClientRelation` non persistiti non è possibile via Livewire; copri il comportamento con i test di Task 3 e verifica manuale dell'azione (vedi Task 8).

- [ ] **Step 6: Pint e commit** — `feat(kyc): relation manager KYC sulla scheda cliente`.

---

### Task 6: Colonna e filtro nella lista clienti + badge nel tab AML

**Files:**
- Modify: `app/Filament/Resources/Clients/Tables/ClientsTable.php`, `app/Filament/Resources/Clients/Schemas/ClientForm.php` (tab "Compliance AML", dopo la Section "Valutazione Rischio (AML)")
- Test: `tests/Feature/KycClientsTableTest.php`

**Interfaces:**
- Consumes: Task 2 (`KycQuestionnaire::coverageByClient()`, `Client::kycCoverage()`).

- [ ] **Step 1: Test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KycClientsTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(string $name): Client
    {
        return Client::create([
            'name' => $name, 'first_name' => 'X', 'is_person' => true, 'is_company' => false,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    public function test_kyc_filter_splits_missing_expired_and_complete_clients(): void
    {
        $this->actingAs(User::factory()->create());
        $missing = $this->makeClient('Missing');
        $expired = $this->makeClient('Expired');
        $complete = $this->makeClient('Complete');
        KycQuestionnaire::factory()->approved()->create(['client_id' => $expired->id, 'next_review_at' => now()->subDay()]);
        KycQuestionnaire::factory()->approved()->create(['client_id' => $complete->id]);

        Livewire::test(ListClients::class)
            ->filterTable('kyc', 'missing')
            ->assertCanSeeTableRecords([$missing])
            ->assertCanNotSeeTableRecords([$expired, $complete])
            ->filterTable('kyc', 'expired')
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$missing, $complete])
            ->filterTable('kyc', 'complete')
            ->assertCanSeeTableRecords([$complete])
            ->assertCanNotSeeTableRecords([$missing, $expired]);
    }
}
```

Nota: il `TernaryFilter` "Consulenti" ha `default(false)`; i client del test hanno `is_company = false`, quindi sono visibili con il filtro di default.

- [ ] **Step 2: Esegui, atteso FAIL**

- [ ] **Step 3: Implementa**

In `ClientsTable::columns()`, dopo la colonna `is_art108`:

```php
TextColumn::make('kyc')
    ->label('KYC')
    ->badge()
    ->state(fn (Client $record) => $record->kycCoverage())
    ->formatStateUsing(fn (KycCoverage $state) => $state->getLabel())
    ->color(fn (KycCoverage $state) => $state->getColor()),
```

In `->filters([...])`:

```php
SelectFilter::make('kyc')
    ->label('KYC')
    ->options(KycCoverage::class)
    ->query(function (Builder $query, array $data) {
        $value = $data['value'] ?? null;

        if (! $value) {
            return $query;
        }

        $coverage = KycQuestionnaire::coverageByClient();

        if ($value === KycCoverage::MISSING->value) {
            return $query->whereNotIn('id', $coverage->keys()->all());
        }

        $ids = $coverage->filter(fn (KycCoverage $c) => $c->value === $value)->keys()->all();

        return $query->whereIn('id', $ids);
    }),
```

(Gli id vengono passati come array, mai come subquery: `Client` è su un'altra connessione.)

In `ClientForm`, tab "Compliance AML", nuova `Section::make('KYC / Adeguata verifica')` con un componente testuale (verifica la classe esistente in v5: `Filament\Schemas\Components\Text`) che mostra `$record?->kycCoverage()->getLabel()` e, se presente, "verificato il … da …; prossima revisione …"; visibile solo su record esistenti.

- [ ] **Step 4: Esegui, atteso PASS**

- [ ] **Step 5: Pint e commit** — `feat(kyc): colonna e filtro KYC nella lista clienti`.

---

### Task 7: Avviso KYC sulla pratica

**Files:**
- Modify: `app/Filament/Resources/Praticas/Pages/EditPratica.php`
- Test: `tests/Feature/KycPraticaNoticeTest.php`

**Interfaces:**
- Consumes: `Client::kycCoverage()`. Produce un testo in `getSubheading()`; nessun blocco.

- [ ] **Step 1: Test che fallisce**

Usa lo schema di creazione pratica di `tests/Feature/ClientEmployerIbanTest.php` (`Pratica::create(['id' => uuid, 'codice_pratica' => ..., 'codice_fiscale' => $client->tax_code])`).

```php
public function test_edit_pratica_warns_when_client_kyc_is_missing(): void
{
    $this->actingAs(User::factory()->create());
    $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);
    $pratica = Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'P-KYC-1', 'codice_fiscale' => $client->tax_code]);

    Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
        ->assertSee('KYC mancante');
}

public function test_edit_pratica_shows_no_warning_when_kyc_is_complete(): void
{
    // stesso setup + KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id]);
    // ->assertDontSee('KYC mancante')->assertDontSee('KYC scaduto')
}
```

(Completa il secondo test con il setup completo; stesso file con `use` e `connectionsToTransact` come gli altri.)

- [ ] **Step 2: Esegui, atteso FAIL**

- [ ] **Step 3: Implementa** in `EditPratica`

```php
public function getSubheading(): ?string
{
    $client = Client::where('tax_code', $this->getRecord()->codice_fiscale)->first();
    $coverage = $client?->kycCoverage();

    return match ($coverage) {
        KycCoverage::MISSING => 'Attenzione: KYC mancante per il cliente',
        KycCoverage::EXPIRED => 'Attenzione: KYC scaduto per il cliente',
        default => null,
    };
}
```

- [ ] **Step 4: Esegui, atteso PASS**

- [ ] **Step 5: Pint e commit** — `feat(kyc): avviso KYC mancante/scaduto sulla pratica`.

---

### Task 8: Verifica finale

- [ ] **Step 1:** `php artisan test --compact tests/Feature/Kyc*.php tests/Unit/BeneficialOwnerSuggesterTest.php` → tutto verde.
- [ ] **Step 2:** `php artisan migrate:status | grep kyc` sulla base dati di sviluppo (chiedi conferma prima di eseguire `migrate` in sviluppo).
- [ ] **Step 3: Verifica manuale** nel browser: scheda di una società → tab KYC → "Precompila da cariche sociali" → salva → "Approva". Nota: `Client::companyRelations` usa `company_id = client.id`, ma `client_relations.company_id` ha una FK verso `companies.id` (uuid). Se in dati reali non produce righe, segnalarlo: la causa è nel modello esistente, non nel KYC.
- [ ] **Step 4:** chiedere all'utente se eseguire l'intera suite (`php artisan test --compact`).

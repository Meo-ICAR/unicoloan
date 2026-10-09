# KYC Adeguata Verifica (QAV) Implementation Plan — rev. 2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Questionario KYC versionato per cliente che ricalca i QAV ufficiali (persona fisica / giuridica con titolari effettivi), stampabile sui due QAV via `PdfModule`, con scadenza e promemoria gestiti da `DocumentType`.

**Architecture:** Tabelle `kyc_questionnaires` / `kyc_beneficial_owners` su `mysql`; enum con le opzioni dei QAV; chiavi `kyc.*` nella whitelist dei moduli PDF e `checkbox_when` con uguaglianza (`chiave=valore`); `KycApprover` genera il QAV compilato come `Document` del cliente (tipo QAV del catalogo) e da lì derivano scadenza e copertura; RelationManager Filament sul cliente.

**Tech Stack:** Laravel 13, Filament v5, Livewire v4, PHPUnit 12, `mikehaertl/php-pdftk` + `pdftk-java` (già presenti).

**Spec:** `docs/superpowers/specs/2026-10-09-kyc-adeguata-verifica-design.md`

## Global Constraints

- Soglia titolare effettivo: quota **strettamente > 25%**; il QAV stampa **al massimo 3** titolari (`position` 1–3).
- `Client` e `Pratica` sono su `mysql_proforma`: `client_id`/`executor_client_id` = `unsignedBigInteger`, `pratica_id` = `string(64)`, niente FK native e niente subquery cross-connection (`pluck()` + `whereIn`). Tabelle nuove su `mysql`.
- Enum: `string` backed, `implements HasLabel`, stile `app/Enums/AmlReportStatus.php`; **l'ordine dei case = ordine delle opzioni nel QAV**, così la lettera della casella = indice (a, b, c…; per il settore `a–i, l, m`).
- Mappatura caselle (verificata sul PDF): `1a` = prima opzione della domanda 1, ecc. Valore "spuntata" = stati del PDF (`si`, oppure `No` per le PEP dei titolari 7/9/11 del QAV giuridico) già letto da `modules:sync-fields` in `checkbox_on_value`.
- Test: PHPUnit, `LazilyRefreshDatabase`, `protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];`. Dove serve un PDF compilato senza fixture reale: `$this->mock(PdfFormFiller::class)->shouldReceive('fill')->andReturn('%PDF-1.4 finto')`.
- `pdftk` è ora installato: i test esistenti del sistema moduli devono restare verdi (37 test: `PdfFormFillerTest`, `PraticaModuleGeneratorTest`, `PdfFieldSynchronizerTest`, `PdfModuleDocumentTypeTest`, `PdfModuleSeederTest`, `CheckPrintableModulesCommandTest`).
- Dopo ogni modifica PHP: `vendor/bin/pint --dirty --format agent`. Nessuna nuova dipendenza. Solo `php artisan make:*` con `--no-interaction`. Testi UI in italiano. Commit con trailer `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Solo informativo: nessun blocco delle pratiche.

## Review Focus

- Cliente con più questionari approvati: conta l'ultimo; una bozza più recente non annulla l'approvato (Task 2).
- Approvato senza documento o con documento senza scadenza: non scade (Task 2).
- Quota esattamente 25.00 non è titolare; carica terminata ignorata; nessuno sopra soglia → residuale col rappresentante legale; senza rappresentante → lista vuota (Task 3).
- Società con >3 titolari: l'approvazione avvisa ma non stampa il quarto (Task 4/6).
- Società senza titolari o con titolari non verificati: approvazione rifiutata con elenco dei requisiti (Task 4).
- Tipo QAV non collegato al modulo PDF: approvazione fallisce con messaggio chiaro e **senza** lasciare stato `approvato` né documenti parziali (Task 6).
- `checkbox_when` senza `=`, con `!`, e con `=` retro-compatibili (Task 5).

---

### Task 1: Enum, migrazioni, modelli, factory

**Files:**
- Create in `app/Enums/` (nessuna sottocartella nuova, prefisso `Kyc`): `KycPepStatus`, `KycEconomicActivity`, `KycActivitySector`, `KycActivityLocation`, `KycFinancingNature`, `KycPersonPurpose`, `KycCompanyPurpose`, `KycIncomeBand`, `KycWealthBand`, `KycLegalNature`, `KycGeographicArea`, `KycExecutorLink`, `KycControlCriterion`, `KycRiskLevel`, `KycStatus`, `KycCoverage`
- Create (artisan): `app/Models/KycQuestionnaire.php`, `app/Models/KycBeneficialOwner.php`, migrazioni, `database/factories/KycQuestionnaireFactory.php`
- Test: `tests/Feature/KycModelTest.php`

**Interfaces — Produces** (valori stringa = quelli indicati, ordine = ordine nel QAV):
- `KycPepStatus`: `PublicOffice='carica_pubblica'`, `DirectFamily='familiare'`, `CloseTies='stretti_legami'`, `LocalOffice='carica_locale'`, `None='nessuna'`
- `KycEconomicActivity`: `Employee='dipendente'`, `SelfEmployed='autonomo'`, `Professional='libero_professionista'`, `Entrepreneur='imprenditore'`, `Retired='pensionato'`, `NonProfessional='non_professionale'`
- `KycActivitySector` (11): `Commerce='commercio_servizi'`, `PublicAdmin='pubblica_amministrazione'`, `Construction='edilizia'`, `Finance='credito_finanza'`, `Industry='industria'`, `Tourism='turismo'`, `Jewelry='gioielli_antiquariato'`, `Waste='rifiuti'`, `Renewables='energie_rinnovabili'`, `OtherRisk='altre_attivita_rischio'`, `None='nessuna_condizione'`
- `KycActivityLocation` (5): `Region='regione_residenza'`, `ItalyMulti='piu_regioni'`, `Eu='unione_europea'`, `NonEu='extra_ue'`, `HighRisk='paesi_alto_rischio'`
- `KycFinancingNature` (4): `SalaryAssignment='cessione_quinto'`, `PersonalLoan='prestito_personale'`, `Mortgage='mutuo'`, `TfsAdvance='anticipo_tfs'`
- `KycPersonPurpose` (2): `Personal='personale_familiare'`, `Professional='professionale_commerciale'`
- `KycIncomeBand` (4): `Up100k='fino_100k'`, `From100kTo250k='100k_250k'`, `From250kTo500k='250k_500k'`, `Over500k='oltre_500k'`
- `KycWealthBand` (3): `Up500k='fino_500k'`, `From500kTo2500k='500k_2500k'`, `Over2500k='oltre_2500k'`
- `KycLegalNature` (5): `SoleProprietorship='ditta_individuale'`, `CapitalCompany='societa_capitali'`, `Partnership='societa_persone'`, `Cooperative='cooperativa_consorzio'`, `Listed='quotata'`
- `KycGeographicArea` (3): `Italy='italia'`, `Eu='ue'`, `NonEu='extra_ue'`
- `KycCompanyPurpose` (10): `FinancialNeeds='fabbisogno_finanziario'`, `ConsortiumGuarantee='garanzia_consortile'`, `CreditLine='apertura_credito'`, `MortgageSecured='mutuo_ipotecario'`, `MortgageUnsecured='mutuo_chirografario'`, `ReceivablesAdvance='anticipo_crediti'`, `PortfolioDiscount='sconto_portafoglio'`, `RealEstateLeasing='leasing_immobiliare'`, `EquipmentLeasing='leasing_strumentale'`, `Factoring='factoring'`
- `KycExecutorLink` (2): `LegalRepresentative='rappresentante_legale'`, `Delegate='delegato'`
- `KycControlCriterion` (5): `Shares='quota_25'`, `MajorityVotes='maggioranza_voti'`, `DominantVotes='influenza_voti'`, `DominantContract='influenza_contratti'`, `Management='poteri_amministrazione'`
- `KycRiskLevel`: `Low='low'`, `Medium='medium'`, `High='high'`; `KycStatus`: `Draft='draft'`, `Complete='complete'`, `Approved='approved'`; `KycCoverage`: `Missing='missing'`, `Expired='expired'`, `Complete='complete'` con `getColor(): string` (danger/warning/success).
- Ogni enum ha `getLabel(): string` con il testo del QAV in italiano (es. `KycPepStatus::PublicOffice` → "Ricopre o ha ricoperto nell'ultimo anno un'importante carica pubblica").
- `KycQuestionnaire` fillable: `client_id, client_mandate_id, pratica_id, document_id, pep_status, financing_purpose, risk_level, status, compiled_at, verified_by, verified_at, notes, economic_activity, activity_sector, activity_location, financing_nature, income_band, wealth_band, acts_for_third_party, legal_nature, geographic_area, executor_client_id, executor_link, executor_pep_status`; relazioni `client()`, `executor()` (BelongsTo `Client` su `executor_client_id`), `document()` (BelongsTo `Document`), `beneficialOwners()` (HasMany, `orderBy('position')`); factory con stati `draft()` e `approved()`.
- `KycBeneficialOwner` fillable: `kyc_questionnaire_id, position, client_id, shares_percentage, control_criterion, pep_status, declaration_signed_at, document_id, is_verified`; relazioni `questionnaire()`, `person()` (BelongsTo `Client` su `client_id`).

- [ ] **Step 1: Test che fallisce**

```php
<?php

namespace Tests\Feature;

use App\Enums\KycControlCriterion;
use App\Enums\KycPepStatus;
use App\Enums\KycStatus;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KycModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    public function test_questionnaire_casts_and_owners_are_ordered_by_position(): void
    {
        $questionnaire = KycQuestionnaire::factory()->create(['client_id' => 1, 'pep_status' => KycPepStatus::None]);
        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id, 'position' => 2, 'client_id' => 11,
            'control_criterion' => KycControlCriterion::Management,
        ]);
        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id, 'position' => 1, 'client_id' => 10,
            'shares_percentage' => 40, 'control_criterion' => KycControlCriterion::Shares,
        ]);

        $questionnaire->refresh();

        $this->assertSame(KycPepStatus::None, $questionnaire->pep_status);
        $this->assertSame(KycStatus::Draft, $questionnaire->status);
        $this->assertSame([10, 11], $questionnaire->beneficialOwners->pluck('client_id')->all());
        $this->assertSame(KycControlCriterion::Shares, $questionnaire->beneficialOwners->first()->control_criterion);
        $this->assertFalse($questionnaire->beneficialOwners->first()->is_verified);
    }

    public function test_enum_order_matches_the_qav_letters(): void
    {
        $this->assertSame(
            ['carica_pubblica', 'familiare', 'stretti_legami', 'carica_locale', 'nessuna'],
            array_column(KycPepStatus::cases(), 'value'),
        );
        $this->assertCount(11, \App\Enums\KycActivitySector::cases());
        $this->assertCount(10, \App\Enums\KycCompanyPurpose::cases());
        $this->assertCount(5, KycControlCriterion::cases());
    }
}
```

- [ ] **Step 2: Esegui, atteso FAIL** — `php artisan test --compact tests/Feature/KycModelTest.php`
- [ ] **Step 3: Implementa** — `php artisan make:model KycQuestionnaire -mf --no-interaction`, `php artisan make:model KycBeneficialOwner -m --no-interaction`; rinomina migrazioni in `2026_10_09_200000_create_kyc_questionnaires_table.php` e `…200100_create_kyc_beneficial_owners_table.php`, con `if (Schema::hasTable(...)) { return; }`:

```php
Schema::create('kyc_questionnaires', function (Blueprint $table) {
    $table->comment('Questionari di adeguata verifica (QAV): un record per compilazione.');
    $table->id();
    $table->unsignedBigInteger('client_id')->index()->comment('mysql_proforma.clients.id');
    $table->foreignId('client_mandate_id')->nullable()->constrained('client_mandates')->nullOnDelete();
    $table->string('pratica_id', 64)->nullable()->comment('mysql_proforma.pratiches.id');
    $table->char('document_id', 36)->nullable()->index()->comment('documents.id: QAV generato');
    $table->string('pep_status')->nullable();
    $table->string('financing_purpose')->nullable();
    $table->string('risk_level')->nullable();
    $table->string('status')->default('draft')->index();
    $table->dateTime('compiled_at')->nullable();
    $table->string('verified_by')->nullable();
    $table->dateTime('verified_at')->nullable();
    $table->text('notes')->nullable();
    // persona fisica
    $table->string('economic_activity')->nullable();
    $table->string('activity_sector')->nullable();
    $table->string('activity_location')->nullable();
    $table->string('financing_nature')->nullable();
    $table->string('income_band')->nullable();
    $table->string('wealth_band')->nullable();
    $table->boolean('acts_for_third_party')->default(false);
    // persona giuridica
    $table->string('legal_nature')->nullable();
    $table->string('geographic_area')->nullable();
    $table->unsignedBigInteger('executor_client_id')->nullable()->comment('mysql_proforma.clients.id');
    $table->string('executor_link')->nullable();
    $table->string('executor_pep_status')->nullable();
    $table->timestamps();
});

Schema::create('kyc_beneficial_owners', function (Blueprint $table) {
    $table->comment('Titolari effettivi dichiarati nel QAV (max 3 stampabili).');
    $table->id();
    $table->foreignId('kyc_questionnaire_id')->constrained('kyc_questionnaires')->cascadeOnDelete();
    $table->unsignedTinyInteger('position')->default(1);
    $table->unsignedBigInteger('client_id')->index()->comment('persona fisica, mysql_proforma.clients.id');
    $table->decimal('shares_percentage', 5, 2)->nullable();
    $table->string('control_criterion');
    $table->string('pep_status')->nullable();
    $table->dateTime('declaration_signed_at')->nullable();
    $table->char('document_id', 36)->nullable()->comment('documents.id');
    $table->boolean('is_verified')->default(false);
    $table->timestamps();
});
```

Modelli: `$fillable`, casts (enum per ogni colonna enum — `financing_purpose` resta stringa —, booleani, datetime, `decimal:2`), relazioni come in Interfaces; `beneficialOwners()` = `hasMany(KycBeneficialOwner::class)->orderBy('position')`. Factory: `client_id => 1`, `status => KycStatus::Draft`; `approved()` = `status Approved, risk_level Medium, verified_at now(), verified_by 'Tester', pep_status None`.
- [ ] **Step 4: Esegui, atteso PASS**; **Step 5:** pint + commit `feat(kyc): enum QAV, tabelle e modelli`.

---

### Task 2: Copertura KYC (scadenza dal Document)

**Files:** Modify `app/Models/KycQuestionnaire.php`, `app/Models/Client.php`; Test `tests/Feature/KycCoverageTest.php`

**Interfaces — Consumes:** Task 1. **Produces:** `KycQuestionnaire::scopeApproved`, `scopeLatestFirst` (`verified_at` desc, `id` desc), `coverage(): KycCoverage`, `static coverageByClient(): Collection<int, KycCoverage>`; `Client::kycQuestionnaires(): HasMany`, `currentKyc(): ?KycQuestionnaire`, `kycCoverage(): KycCoverage`.

Regola: `coverage()` = `Expired` se `$this->document?->expires_at` è nel passato (`< today()`), altrimenti `Complete`. `kycCoverage()` = `Missing` se nessun approvato.

- [ ] **Step 1: Test che fallisce** — helper nel test: `makeClient()` come negli altri test (`Client::create([...tax_code random])`); `approvedWithExpiry(Client $c, ?Carbon $expires, string $verifiedAt = 'now')`: crea `KycQuestionnaire::factory()->approved()`; se `$expires !== null` crea `$doc = $c->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']); $doc->forceFill(['expires_at' => $expires])->saveQuietly();` e imposta `document_id`. Casi: nessun questionario → `Missing`; solo bozza → `Missing`; approvato senza documento → `Complete`; approvato con `expires_at` ieri → `Expired`; con `expires_at` fra un anno → `Complete`; vecchio approvato scaduto + nuovo approvato valido + bozza ancora più recente → `Complete`; `coverageByClient()` contiene solo i clienti con un approvato (bozza esclusa) e mappa `Expired`/`Complete` correttamente.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**

```php
// KycQuestionnaire
public function scopeApproved(Builder $query): Builder
{
    return $query->where('status', KycStatus::Approved);
}

public function scopeLatestFirst(Builder $query): Builder
{
    return $query->orderByDesc('verified_at')->orderByDesc('id');
}

public function coverage(): KycCoverage
{
    $expiresAt = $this->document?->expires_at;

    return $expiresAt !== null && $expiresAt->lt(today()) ? KycCoverage::Expired : KycCoverage::Complete;
}

/**
 * @return Collection<int, KycCoverage>
 */
public static function coverageByClient(): Collection
{
    return static::approved()->with('document')->latestFirst()->get()
        ->unique('client_id')
        ->mapWithKeys(fn (self $q) => [$q->client_id => $q->coverage()]);
}

// Client
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
    return $this->currentKyc()?->coverage() ?? KycCoverage::Missing;
}
```
- [ ] **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): copertura KYC dalla scadenza del documento QAV`.

---

### Task 3: Suggerimento titolari effettivi

**Files:** Create `app/Services/Kyc/BeneficialOwnerSuggester.php`; Test `tests/Unit/BeneficialOwnerSuggesterTest.php` (estende `PHPUnit\Framework\TestCase`, nessun DB)

**Interfaces — Produces:** `BeneficialOwnerSuggester::THRESHOLD = 25.0`; `suggest(Collection $relations, ?int $legalRepresentativeId): array` → lista di `array{client_id: int, position: int, shares_percentage: float|null, control_criterion: KycControlCriterion}`, max 3 elementi, ordinati per quota decrescente, `position` 1..n. `$relations` = `ClientRelation` (anche non persistiti).

- [ ] **Step 1: Test che fallisce** — stesso schema del test della v1 (helper `relation(int $clientId, ?float $shares, ?string $endedAt = null)` = `new ClientRelation(['client_id'=>…, 'shares_percentage'=>…, 'data_fine_ruolo'=>…])`): solo quote `> 25` (60, 25.01 sì; 25.00 e `null` no) → `client_id` `[1, 3]` ordinati per quota (60 prima), `position` `[1, 2]`, criterio `Shares`; ruolo con `data_fine_ruolo` nel passato ignorato; nessuno sopra soglia con rappresentante 9 → `[['client_id'=>9, 'position'=>1, 'shares_percentage'=>null, 'control_criterion'=>Management]]`; nessuno e senza rappresentante → `[]`; 5 titolari sopra soglia → solo 3.
- [ ] **Step 2: FAIL**; **Step 3: Implementa**

```php
<?php

namespace App\Services\Kyc;

use App\Enums\KycControlCriterion;
use App\Models\ClientRelation;
use Illuminate\Support\Collection;

class BeneficialOwnerSuggester
{
    public const THRESHOLD = 25.0;

    public const MAX_OWNERS = 3;

    /**
     * @param  Collection<int, ClientRelation>  $relations
     * @return array<int, array{client_id: int, position: int, shares_percentage: float|null, control_criterion: KycControlCriterion}>
     */
    public function suggest(Collection $relations, ?int $legalRepresentativeId): array
    {
        $owners = $relations
            ->filter(fn (ClientRelation $r) => $r->data_fine_ruolo === null || $r->data_fine_ruolo->isFuture())
            ->filter(fn (ClientRelation $r) => (float) $r->shares_percentage > self::THRESHOLD)
            ->sortByDesc(fn (ClientRelation $r) => (float) $r->shares_percentage)
            ->take(self::MAX_OWNERS)
            ->values()
            ->map(fn (ClientRelation $r, int $index) => [
                'client_id' => (int) $r->client_id,
                'position' => $index + 1,
                'shares_percentage' => (float) $r->shares_percentage,
                'control_criterion' => KycControlCriterion::Shares,
            ])
            ->all();

        if ($owners === [] && $legalRepresentativeId !== null) {
            return [[
                'client_id' => $legalRepresentativeId,
                'position' => 1,
                'shares_percentage' => null,
                'control_criterion' => KycControlCriterion::Management,
            ]];
        }

        return $owners;
    }
}
```
- [ ] **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): suggerimento titolari effettivi`.

---

### Task 4: Requisiti di completezza

**Files:** Modify `app/Models/KycQuestionnaire.php`; Test `tests/Feature/KycRequirementsTest.php`

**Interfaces — Produces:** `KycQuestionnaire::missingRequirements(): array<int, string>` (vuoto = approvabile).

Requisiti — **persona fisica** (`client->is_person`): `pep_status`, `economic_activity`, `activity_sector`, `activity_location`, `financing_nature`, `financing_purpose`, `income_band`, `wealth_band`, `risk_level`; se `acts_for_third_party` almeno un titolare effettivo. **Persona giuridica**: `legal_nature`, `geographic_area`, `financing_purpose`, `executor_client_id`, `executor_link`, `executor_pep_status`, `risk_level`, almeno 1 titolare, ogni titolare con `control_criterion` e `pep_status`, tutti `is_verified`. Con >3 titolari: nessun requisito mancante ma `warnings()` (stringa) — vedi sotto.

- [ ] **Step 1: Test che fallisce** — helper `filledPerson(Client)` / `filledCompany(Client)` che compilano tutti i campi con valori enum validi (`KycPepStatus::None`, …); casi: persona completa → `[]`; persona senza `income_band` → 1 voce con etichetta "Reddito annuo lordo"; persona con `acts_for_third_party` senza titolari → richiede titolare; società completa con 1 titolare verificato → `[]`; società senza titolari → contiene "Almeno un titolare effettivo"; titolare non verificato → contiene "Verifica di tutti i titolari effettivi"; titolare senza `pep_status` → richiesto; società con 4 titolari → `[]` e `warnings()` contiene "più di 3".
- [ ] **Step 2: FAIL**; **Step 3: Implementa** — tabella etichette in costante `FIELD_LABELS` (campo → etichetta italiana) e due liste `PERSON_REQUIRED` / `COMPANY_REQUIRED`:

```php
public function missingRequirements(): array
{
    $isPerson = (bool) $this->client?->is_person;
    $required = $isPerson ? self::PERSON_REQUIRED : self::COMPANY_REQUIRED;

    $missing = collect($required)
        ->filter(fn (string $field) => blank($this->{$field}))
        ->map(fn (string $field) => self::FIELD_LABELS[$field])
        ->values()
        ->all();

    $owners = $this->beneficialOwners;

    if (! $isPerson || $this->acts_for_third_party) {
        if ($owners->isEmpty()) {
            $missing[] = 'Almeno un titolare effettivo';
        } else {
            if ($owners->contains(fn (KycBeneficialOwner $o) => blank($o->control_criterion) || blank($o->pep_status))) {
                $missing[] = 'Criterio e PEP di ogni titolare effettivo';
            }
            if ($owners->contains(fn (KycBeneficialOwner $o) => ! $o->is_verified)) {
                $missing[] = 'Verifica di tutti i titolari effettivi';
            }
        }
    }

    return $missing;
}

/**
 * @return array<int, string>
 */
public function warnings(): array
{
    return $this->beneficialOwners->count() > 3
        ? ['Il QAV stampa al massimo 3 titolari effettivi: gli altri vanno allegati a parte (più di 3 dichiarati).']
        : [];
}
```
- [ ] **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): requisiti di completezza per persona fisica e giuridica`.

---

### Task 5: Chiavi `kyc.*` e `checkbox_when` con uguaglianza

**Files:**
- Modify: `app/Enums/ModuleSourceKey.php`, `app/Services/ModuleDataResolver.php`, `app/Services/ResolvedModuleData.php`, `app/Models/PdfModuleField.php`, `app/Services/PdfFormFiller.php`
- Create: `app/Services/Kyc/KycModuleValues.php`
- Test: `tests/Feature/ModuleKycKeysTest.php`, `tests/Feature/PdfFormFillerEqualityTest.php`

**Interfaces — Produces:**
- Nuovi case di `ModuleSourceKey` (value = `kyc.*`): scalari `kyc.pep_status`, `kyc.financing_purpose`, `kyc.economic_activity`, `kyc.activity_sector`, `kyc.activity_location`, `kyc.financing_nature`, `kyc.income_band`, `kyc.wealth_band`, `kyc.legal_nature`, `kyc.geographic_area`, `kyc.executor_link`; persone `kyc.executor.<f>` per `<f>` in `PERSON_FIELDS` + `kyc.executor.pep_status`; `kyc.owner{1,2,3}.<f>` per `<f>` in `PERSON_FIELDS` + `kyc.owner{n}.criterion`, `kyc.owner{n}.pep_status`.
- `KycModuleValues::PERSON_FIELDS` = `['name','first_name','tax_code','birth_place','birth_date','citizenship','sex','city','province','address','zip','doc_type','doc_number','doc_issuer','doc_issued_at','doc_expires_at']`.
- `KycModuleValues::for(Client $client, ?KycQuestionnaire $kyc): array<string, mixed>` → mappa `kyc.* => valore` (enum → `->value`, persone da `Client`, sede e documento d'identità con la stessa logica di `ModuleDataResolver`).
- `ModuleDataResolver::resolveForClient(Client $client, ?KycQuestionnaire $kyc = null, ?Pratica $pratica = null): ResolvedModuleData`; `resolve(Pratica, Client)` continua a funzionare e include anche le chiavi `kyc.*` (KYC = `$client->currentKyc()` altrimenti l'ultima bozza).
- `ResolvedModuleData::equals(ModuleSourceKey $key, string $value): bool` (confronto di stringa del valore, enum già come stringa).
- `checkbox_when`: formato `[!]chiave[=valore]`. `PdfModuleField::referencedKeys()` e `PdfFormFiller::isCheckboxChecked()` lo interpretano: senza `=` comportamento invariato; con `=` → `equals()`; `!` nega.

- [ ] **Step 1: Test che fallisce (uguaglianza)** — `PdfFormFillerEqualityTest`: con `ResolvedModuleData(['kyc.pep_status' => 'nessuna'])` e due `PdfModuleField` checkbox non persistiti (`new PdfModuleField(['pdf_field_type'=>'checkbox','checkbox_on_value'=>'si','checkbox_when'=>'kyc.pep_status=nessuna'])`, `…=carica_pubblica`, `!kyc.pep_status=nessuna`, `client.is_person` invariato) e `(new PdfModule)->setRelation('fields', collect([...]))`, verifica `buildFieldValues()` → `['f1'=>'si','f2'=>'Off',…]`; verifica anche `referencedKeys()` restituisce `[ModuleSourceKey::KycPepStatus]` per `kyc.pep_status=nessuna` e per `!kyc.pep_status=nessuna`.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** — in `PdfModuleField` aggiungi:

```php
/**
 * @return array{negate: bool, key: ModuleSourceKey|null, value: string|null}
 */
public function parseCheckboxWhen(): array
{
    $when = (string) $this->checkbox_when;
    $negate = str_starts_with($when, '!');
    [$name, $value] = array_pad(explode('=', ltrim($when, '!'), 2), 2, null);

    return ['negate' => $negate, 'key' => ModuleSourceKey::tryFrom($name), 'value' => $value];
}
```
usalo in `referencedKeys()` e in `PdfFormFiller::isCheckboxChecked()`:

```php
if (filled($field->checkbox_when)) {
    ['negate' => $negate, 'key' => $key, 'value' => $value] = $field->parseCheckboxWhen();

    if ($key === null) {
        return null;
    }

    $matches = $value === null ? $data->isTruthy($key) : $data->equals($key, $value);

    return $matches !== $negate;
}
```
`ResolvedModuleData::equals()`: `return (string) ($this->get($key) instanceof \BackedEnum ? $this->get($key)->value : $this->get($key)) === $value;` (valori nulli → `''`).
- [ ] **Step 4: PASS**; **Step 5: Test chiavi/resolver** — `ModuleKycKeysTest`: (a) coerenza: per ogni `ModuleSourceKey` con value che inizia per `kyc.` esiste in `KycModuleValues::for(...)` e viceversa (nessuna chiave orfana in nessuna direzione); (b) `resolveForClient($company, $kyc)` espone `kyc.legal_nature`, `kyc.executor.name` (nome dell'esecutore), `kyc.owner1.tax_code`, `kyc.owner1.criterion`, `kyc.owner2.name` null se non c'è il secondo titolare; (c) la persona `sex`, `birth_date` come in `Client`; (d) `resolve($pratica, $client)` continua a restituire `pratica.codice_pratica` e ora anche `kyc.pep_status` dal KYC approvato.
- [ ] **Step 6: Genera i case dell'enum con uno script monouso** (output da incollare in `ModuleSourceKey` e usare per le etichette):

```bash
php -r '
$f = ["name","first_name","tax_code","birth_place","birth_date","citizenship","sex","city","province","address","zip","doc_type","doc_number","doc_issuer","doc_issued_at","doc_expires_at"];
$cases = [];
foreach (["pep_status","financing_purpose","economic_activity","activity_sector","activity_location","financing_nature","income_band","wealth_band","legal_nature","geographic_area","executor_link"] as $k) { $cases[] = "kyc.$k"; }
foreach (["executor" => ["pep_status"], "owner1" => ["criterion","pep_status"], "owner2" => ["criterion","pep_status"], "owner3" => ["criterion","pep_status"]] as $s => $extra) {
    foreach (array_merge($f, $extra) as $x) { $cases[] = "kyc.$s.$x"; }
}
foreach ($cases as $c) { $n = str_replace(" ", "", ucwords(str_replace([".", "_"], " ", $c))); echo "    case $n = \x27$c\x27;\n"; }
'
```
Incolla l'output nell'enum e aggiungi al `match` di `getLabel()` un ramo finale `default => (string) \Illuminate\Support\Str::of($this->value)->after('kyc.')->replace(['.', '_'], ' ')->prepend('KYC: ')` (l'enum ha già un `match` esaustivo: il `default` copre tutti i `kyc.*`).
- [ ] **Step 7: Implementa `KycModuleValues` e `resolveForClient`** — `KycModuleValues::for()`: `$map = []`; scalari: `$map['kyc.pep_status'] = $kyc?->pep_status?->value;` … (uno per scalare; `financing_purpose` è già stringa); `foreach (['executor' => $kyc?->executor, 'owner1' => …, 'owner2' => …, 'owner3' => …] as $subject => $person)` → `personBlock($subject, $person)`; owner N = `$kyc->beneficialOwners->firstWhere('position', N)` con `->person`; `kyc.owner{N}.criterion` = `$owner?->control_criterion?->value`, `…pep_status` = `$owner?->pep_status?->value`, `kyc.executor.pep_status` = `$kyc?->executor_pep_status?->value`. `personBlock` riusa gli helper del resolver: **estrai** `mainBranch()`, `fullAddress()`, `identityDocument()` da `ModuleDataResolver` in metodi `public` e passa il resolver a `KycModuleValues` (costruttore) oppure — più semplice — rendi `KycModuleValues` un metodo privato del resolver e lascia la costante `PERSON_FIELDS` in `KycModuleValues`; scegli la seconda, e documenta la scelta nel codice. In `resolve()` e `resolveForClient()`: `$kyc ??= $client->currentKyc() ?? $client->kycQuestionnaires()->latest('id')->first();` e `array_merge($values, $this->kycValues($client, $kyc))`. `resolveForClient` omette le chiavi `pratica.*` quando `$pratica === null` (valori nulli).
- [ ] **Step 8:** esegui `php artisan test --compact tests/Feature/ModuleKycKeysTest.php tests/Feature/PdfFormFillerEqualityTest.php tests/Feature/PdfFormFillerTest.php tests/Feature/ModuleDataResolverClientLookupTest.php tests/Feature/PraticaModuleGeneratorTest.php` → PASS; pint + commit `feat(moduli): chiavi kyc.* e checkbox_when con uguaglianza`.

---

### Task 6: Generazione QAV, approvazione e mappatura dei campi

**Files:**
- Create: `app/Services/Kyc/KycQavGenerator.php`, `app/Services/Kyc/KycApprover.php`, `database/seeders/KycQavSeeder.php`
- Test: `tests/Feature/KycApprovalTest.php`, `tests/Feature/KycQavSeederTest.php`

**Interfaces**
- Consumes: Task 1–5, `PdfFormFiller::fill(PdfModule, ResolvedModuleData): string`, `PdfModule::documentType`, `ModuleDataResolver::resolveForClient`.
- Produces:
  - `KycQavGenerator::SLUG_PERSON = 'qav-persona-fisica'`, `SLUG_COMPANY = 'qav-persona-giuridica'`; `moduleFor(Client): ?PdfModule` (modulo attivo il cui `documentType.slug` è lo slug del tipo cliente); `render(KycQuestionnaire): string` (PDF compilato, senza salvare); `generate(KycQuestionnaire, ?User): Document`.
  - `KycApprover::approve(KycQuestionnaire, User): Document` — lancia `\DomainException` con requisiti mancanti o con "Modulo QAV non configurato per il tipo di cliente".
  - `KycQavSeeder` idempotente.

Flusso `approve`: (1) `missingRequirements()` ≠ vuoto → `DomainException`; (2) `generate()` dentro `DB::transaction` (crea `Document` del cliente con `document_type_id` del modulo, `emitted_at = today()`, `status = DocumentStatus::UPLOADED`, media `documents`, activity log `kyc_qav` sul cliente senza valori sensibili); (3) solo se (2) riesce: `status = Approved`, `verified_by = $user->name`, `verified_at = now()`, `document_id`. Un errore a qualsiasi passo lascia `status` invariato e nessun `Document`.

- [ ] **Step 1: Test che fallisce (approvazione)** — con `PdfFormFiller` mockato:
  - setup comune: `PdfModule` "QAV Persona fisica" collegato a un `DocumentType` con `slug = 'qav-persona-fisica'`, `is_monitored = true`, `duration = 12`, `duration_unit = 'months'`; cliente persona fisica con questionario completo (helper del Task 4).
  - `test_approve_creates_the_qav_document_and_marks_approved`: dopo `approve()` → `status Approved`, `verified_by` = nome utente, `document_id` valorizzato, il `Document` appartiene al cliente (`documentable`), ha `document_type_id` del tipo, `expires_at` = `emitted_at + 12 mesi` (≈ `today()->addMonths(12)`), media `documents` presente; `$client->kycCoverage() === KycCoverage::Complete`.
  - `test_incomplete_questionnaire_is_rejected_without_side_effects`: `DomainException`, `Document::count() === 0`, `status` ancora `Draft`.
  - `test_missing_qav_module_is_rejected_without_side_effects`: nessun `PdfModule` → `DomainException` "Modulo QAV non configurato…", `status` ancora `Draft`.
  - `test_company_uses_the_company_module`: cliente `is_person = false` con titolare verificato → usa il modulo con slug `qav-persona-giuridica`.
  - `test_filler_failure_leaves_nothing_behind`: il mock lancia `PdfFormException` → `Document::count() === 0`, `status` ancora `Draft`.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** `KycQavGenerator`:

```php
public function moduleFor(Client $client): ?PdfModule
{
    $slug = $client->is_person ? self::SLUG_PERSON : self::SLUG_COMPANY;

    return PdfModule::query()->active()
        ->whereHas('documentType', fn ($query) => $query->where('slug', $slug))
        ->first();
}

public function render(KycQuestionnaire $questionnaire): string
{
    $client = $questionnaire->client;
    $module = $this->moduleFor($client)
        ?? throw new \DomainException('Modulo QAV non configurato per il tipo di cliente.');

    return $this->filler->fill($module->loadMissing('fields'), $this->resolver->resolveForClient($client, $questionnaire));
}

public function generate(KycQuestionnaire $questionnaire, ?User $user): Document
{
    $client = $questionnaire->client;
    $module = $this->moduleFor($client)
        ?? throw new \DomainException('Modulo QAV non configurato per il tipo di cliente.');
    $content = $this->render($questionnaire);

    return DB::transaction(function () use ($questionnaire, $client, $module, $content, $user): Document {
        $document = $client->documents()->create([
            'document_type_id' => $module->document_type_id,
            'name' => $module->name.' - '.trim(($client->name ?? '').' '.($client->first_name ?? '')),
            'status' => DocumentStatus::UPLOADED->value,
            'spatie_collection' => 'documents',
            'emitted_at' => today(),
            'uploaded_by' => $user?->getKey(),
            'created_by' => $user?->getKey(),
        ]);

        $document->addMediaFromString($content)
            ->usingFileName(Str::slug($module->name.' '.$client->tax_code).'.pdf')
            ->toMediaCollection('documents');

        activity('kyc_qav')->performedOn($client)->causedBy($user)->event('qav_generato')
            ->withProperties(['questionnaire_id' => $questionnaire->getKey(), 'document_id' => $document->getKey()])
            ->log('QAV generato per il cliente');

        return $document;
    });
}
```
`KycApprover::approve()` come da flusso sopra (il `generate()` fa già il controllo modulo; i requisiti si controllano per primi).
- [ ] **Step 4: PASS**.
- [ ] **Step 5: Seeder `KycQavSeeder`** — (a) per i due moduli QAV (`PdfModule` con `file_path` dei due QAV) chiama `PdfFieldSynchronizer::linkDocumentType()` e, **solo se il tipo non ha già `is_monitored`**, imposta: `is_monitored true`, `duration 12`, `duration_unit 'months'`, `is_signed true`, `is_client true`, `is_practice false`, `document_typable 'cliente'`, `slug` = `KycQavGenerator::SLUG_PERSON/COMPANY` (+ `is_person`/`is_company` coerenti); (b) applica la mappatura campi **solo ai campi senza `source_key` e senza `checkbox_when`**:

```php
private const LETTERS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm'];

// domanda => [chiave scalare, classe enum]
PERSON  = ['1' => ['kyc.pep_status', KycPepStatus::class], '2' => ['kyc.economic_activity', KycEconomicActivity::class],
           '3' => ['kyc.activity_sector', KycActivitySector::class], '4' => ['kyc.activity_location', KycActivityLocation::class],
           '5' => ['kyc.financing_nature', KycFinancingNature::class], '6' => ['kyc.financing_purpose', KycPersonPurpose::class],
           '7' => ['kyc.income_band', KycIncomeBand::class], '8' => ['kyc.wealth_band', KycWealthBand::class]];
COMPANY = ['1' => ['kyc.legal_nature', KycLegalNature::class], '2' => ['kyc.geographic_area', KycGeographicArea::class],
           '3' => ['kyc.financing_purpose', KycCompanyPurpose::class], '4' => ['kyc.executor_link', KycExecutorLink::class],
           '5' => ['kyc.executor.pep_status', KycPepStatus::class],
           '6' => ['kyc.owner1.criterion', KycControlCriterion::class], '7' => ['kyc.owner1.pep_status', KycPepStatus::class],
           '8' => ['kyc.owner2.criterion', KycControlCriterion::class], '9' => ['kyc.owner2.pep_status', KycPepStatus::class],
           '10' => ['kyc.owner3.criterion', KycControlCriterion::class], '11' => ['kyc.owner3.pep_status', KycPepStatus::class]];
// per ogni [domanda, chiave, enum] e ogni case $i: campo "{domanda}{LETTERS[$i]}" -> checkbox_when "{chiave}={case->value}"
```
  Campi di testo (nome campo PDF → chiave; `birth_date`, `doc_issued_at`, `doc_expires_at` con `Fmt::DateIt`), nell'ordine di `PERSON_FIELDS`:
  - **Persona fisica**: nessun campo di testo nuovo (anagrafica già mappata da `PdfModuleFieldMappingSeeder`); `data` → `Key::PraticaOggi` + `DateIt`.
  - **Persona giuridica, esecutore**: `dummyFieldName25, dummyFieldName26, dummyFieldName27, dummyFieldName28, Text22, Text23, Text24, Text25, Text26, Text27, Text28, Text29, Text30, Text31, Text32, Text33` → `kyc.executor.<PERSON_FIELDS[i]>`.
  - **Titolare 1**: `Text39, Text40, Text41, Text42, Text43, Text44, Text45, Text46, Text47, Text48, Text49, Text50, Text51, Text53, Text54, Text55`; **Titolare 2**: `Text59…Text74` (16 campi consecutivi); **Titolare 3**: `Text78…Text93` (16 campi consecutivi) → `kyc.owner{n}.<…>`. Verificato sul PDF diagnostico: esecutore e titolari 1–2; il titolare 3 è per analogia → controllare con `modules:sync-fields --diagnostic`.
  - `Text97` e `Text100` → `PraticaOggi` + `DateIt`. `luogo`, `Text99` (collaboratore) restano a mano.
- [ ] **Step 6: Test del seeder** — `KycQavSeederTest`: dopo `PdfModuleSeeder` e due `PdfModule` QAV con campi creati a mano (o `PdfModuleField::factory()` con i nomi `1a`, `1e`, `3m`, `Text39`, …) il seeder imposta `checkbox_when` = `kyc.pep_status=carica_pubblica` su `1a`, `kyc.activity_sector=nessuna_condizione` su `3m` (indice 10 ↔ lettera `m`), `kyc.owner1.name` su `Text39`; è idempotente; **non** sovrascrive un campo già mappato; imposta durata 12 mesi e `is_monitored` solo se il tipo non lo era.
- [ ] **Step 7:** pint + commit `feat(kyc): approvazione con generazione QAV e mappatura campi`.

---

### Task 7: RelationManager Filament

**Files:** Create `app/Filament/Resources/Clients/RelationManagers/KycQuestionnairesRelationManager.php`; Modify `app/Filament/Resources/Clients/ClientResource.php` (registra l'RM al posto della riga commentata `// ChecklistsRelationManager::class`); Test `tests/Feature/KycRelationManagerTest.php`

**Interfaces — Consumes:** Task 1–6, `BeneficialOwnerSuggester`. Verifica le API v5 con `search-docs` prima di scrivere (`Schemas\Components\Actions`, `Repeater::relationship()`, `TestAction::make(...)->table($record)`). **Non** usare `HasRelationPlanAccess` (nessuna feature key): `canViewForRecord` → `true`.

Form (sezioni visibili in base a `$this->getOwnerRecord()->is_person`):
- *Comune*: `pep_status` (Radio, `KycPepStatus`), `financing_purpose` (Select, opzioni `KycPersonPurpose` o `KycCompanyPurpose` secondo il tipo), `risk_level` (Select), `client_mandate_id` (mandati del cliente), `compiled_at` (default now), `notes`.
- *Persona fisica*: `economic_activity`, `activity_sector`, `activity_location`, `financing_nature`, `income_band`, `wealth_band` (Radio/Select con le enum), `acts_for_third_party` (Toggle).
- *Persona giuridica*: `legal_nature`, `geographic_area`, `executor_client_id` (Select cercabile sulle persone fisiche), `executor_link`, `executor_pep_status`.
- *Titolari effettivi* (persona giuridica, o persona fisica con `acts_for_third_party`): `Actions` con `prefill_owners` ("Precompila da cariche sociali") che usa `BeneficialOwnerSuggester::suggest($client->companyRelations, $client->legal_representative_id)` e fa `$set('beneficialOwners', …)`; `Repeater::make('beneficialOwners')->relationship()->maxItems(3)` con `position` (hidden, ordine), `client_id` (Select), `shares_percentage`, `control_criterion`, `pep_status`, `declaration_signed_at`, `is_verified`.

Tabella: colonne `compiled_at`, `risk_level` badge, `status` badge, `verified_by`, `verified_at`, `document.expires_at` ("Scade il"); `CreateAction` "Nuova compilazione" con `mutateFormDataUsing` → `status = Draft`; record actions: `EditAction`; `approve` (visibile se `status !== Approved`, `requiresConfirmation`, chiama `app(KycApprover::class)->approve($record, auth()->user())` in try/catch `\DomainException` → `Notification::make()->danger()->title('KYC incompleto')->body($e->getMessage())`; successo `->success()->title('KYC approvato')`; se `$record->warnings()` non è vuoto, aggiungili al body); `print` ("Stampa QAV": `response()->streamDownload(fn () => print(app(KycQavGenerator::class)->render($record)), 'QAV.pdf')`, errori `DomainException`/`PdfFormException` → notifica); `DeleteAction` solo se `status !== Approved`.

- [ ] **Step 1: Test che fallisce** — `Livewire::test(KycQuestionnairesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])` con `actingAs(User::factory()->create())`: lista dei questionari; `approve` su un questionario completo (con modulo QAV e filler mockati come nel Task 6) → `status Approved` e notifica; `approve` su un incompleto → notifica e `status` ancora `Draft`; creare una nuova compilazione (`TestAction::make('create')->table()` con i campi minimi per persona fisica) conserva il questionario approvato precedente (`count() === 2`).
- [ ] **Step 2: FAIL**; **Step 3: Implementa**; **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): relation manager KYC sulla scheda cliente`.

---

### Task 8: Lista clienti e tab AML

**Files:** Modify `app/Filament/Resources/Clients/Tables/ClientsTable.php`, `app/Filament/Resources/Clients/Schemas/ClientForm.php` (tab "Compliance AML"); Test `tests/Feature/KycClientsTableTest.php`

**Interfaces — Consumes:** `KycQuestionnaire::coverageByClient()`, `Client::kycCoverage()`.

- [ ] **Step 1: Test che fallisce** — come nella v1: tre clienti (`is_company = false`, visibili col filtro "Consulenti" di default), uno `Missing`, uno `Expired` (approvato con documento scaduto), uno `Complete`; `Livewire::test(ListClients::class)->filterTable('kyc', 'missing'|'expired'|'complete')` mostra solo il cliente atteso.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** — colonna:

```php
TextColumn::make('kyc')->label('KYC')->badge()
    ->state(fn (Client $record) => $record->kycCoverage())
    ->formatStateUsing(fn (KycCoverage $state) => $state->getLabel())
    ->color(fn (KycCoverage $state) => $state->getColor()),
```
filtro `SelectFilter::make('kyc')->options(KycCoverage::class)->query(…)`: `missing` → `whereNotIn('id', $coverage->keys()->all())`; `expired`/`complete` → `whereIn('id', ids con quella copertura)` (array di id, mai subquery). Nel tab AML una `Section` "KYC / Adeguata verifica" visibile sui record esistenti con il testo `Filament\Schemas\Components\Text` (verifica che la classe esista in v5) che mostra la copertura e "verificato il … da …".
- [ ] **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): colonna e filtro KYC nella lista clienti`.

---

### Task 9: Avviso sulla pratica

**Files:** Modify `app/Filament/Resources/Praticas/Pages/EditPratica.php`; Test `tests/Feature/KycPraticaNoticeTest.php`

- [ ] **Step 1: Test che fallisce** — `Pratica::create(['id' => uuid, 'codice_pratica' => 'P-KYC-1', 'codice_fiscale' => $client->tax_code])`; `Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])->assertSee('KYC mancante')` per cliente senza KYC; `assertSee('KYC scaduto')` con documento scaduto; né l'uno né l'altro con KYC completo.
- [ ] **Step 2: FAIL**; **Step 3: Implementa** in `EditPratica`:

```php
public function getSubheading(): ?string
{
    $client = Client::where('tax_code', $this->getRecord()->codice_fiscale)->first();

    return match ($client?->kycCoverage()) {
        KycCoverage::Missing => 'Attenzione: KYC mancante per il cliente',
        KycCoverage::Expired => 'Attenzione: KYC scaduto per il cliente',
        default => null,
    };
}
```
- [ ] **Step 4: PASS**; **Step 5:** pint + commit `feat(kyc): avviso KYC mancante/scaduto sulla pratica`.

---

### Task 10: Verifica finale e messa in servizio

- [ ] **Step 1:** `php artisan test --compact tests/Feature/Kyc*.php tests/Feature/ModuleKycKeysTest.php tests/Feature/PdfFormFillerEqualityTest.php tests/Unit/BeneficialOwnerSuggesterTest.php` e i 37 test del sistema moduli → tutto verde.
- [ ] **Step 2: Messa in servizio sul DB di sviluppo (chiedere conferma prima):** `php artisan migrate`; `php artisan db:seed --class=PdfModuleSeeder`; `php artisan modules:sync-fields --link-document-types`; `php artisan db:seed --class=PdfModuleFieldMappingSeeder`; `php artisan db:seed --class=KycQavSeeder`.
- [ ] **Step 3: Verifica manuale** (browser): società → tab "KYC / Adeguata verifica" → "Precompila da cariche sociali" → compila → "Approva" → scarica il QAV dal documento e controlla con `modules:sync-fields --diagnostic` la posizione dei campi del **titolare 3** (mappato per analogia). Nota: `Client::companyRelations` usa `company_id = client.id` mentre `client_relations.company_id` ha una FK verso `companies.id` (uuid): se in dati reali il precompilato non trova righe, la causa è nel modello esistente, non nel KYC.
- [ ] **Step 4:** chiedere all'utente se eseguire l'intera suite (`php artisan test --compact`; nota: 2 test di `DocumentReminderServiceTest` falliscono già su main per `employees.employee_roles`).

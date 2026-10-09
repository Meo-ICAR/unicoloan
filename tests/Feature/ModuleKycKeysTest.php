<?php

namespace Tests\Feature;

use App\Enums\KycControlCriterion;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\ModuleSourceKey;
use App\Models\Client;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleKycKeysTest extends TestCase
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

    private function companyWithKyc(): array
    {
        $company = $this->makeClient(['name' => 'Acme Srl', 'first_name' => null, 'is_person' => false]);
        $executor = $this->makeClient(['name' => 'Bianchi', 'first_name' => 'Luca', 'sex' => 'M', 'birth_date' => '1980-05-17']);
        $owner = $this->makeClient(['name' => 'Verdi', 'first_name' => 'Anna']);

        $kyc = KycQuestionnaire::factory()->approved()->create([
            'client_id' => $company->id,
            'legal_nature' => KycLegalNature::cases()[0],
            'executor_client_id' => $executor->id,
        ]);
        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $kyc->id,
            'position' => 1,
            'client_id' => $owner->id,
            'control_criterion' => KycControlCriterion::cases()[0],
            'pep_status' => KycPepStatus::None,
        ]);

        return [$company, $kyc, $executor, $owner];
    }

    public function test_enum_kyc_cases_match_resolver_keys_in_both_directions(): void
    {
        [$company, $kyc] = $this->companyWithKyc();

        $resolverKeys = collect(array_keys(app(ModuleDataResolver::class)->resolveForClient($company, $kyc)->values))
            ->filter(fn (string $key) => str_starts_with($key, 'kyc.'))
            ->sort()->values()->all();

        $enumKeys = collect(ModuleSourceKey::cases())
            ->map(fn (ModuleSourceKey $case) => $case->value)
            ->filter(fn (string $key) => str_starts_with($key, 'kyc.'))
            ->sort()->values()->all();

        $this->assertSame($enumKeys, $resolverKeys);
    }

    public function test_resolve_for_client_exposes_kyc_values(): void
    {
        [$company, $kyc, $executor, $owner] = $this->companyWithKyc();

        $data = app(ModuleDataResolver::class)->resolveForClient($company, $kyc);

        $this->assertSame(KycLegalNature::cases()[0]->value, $data->get(ModuleSourceKey::KycLegalNature));
        $this->assertSame('Bianchi', $data->get(ModuleSourceKey::KycExecutorName));
        $this->assertSame($owner->tax_code, $data->get(ModuleSourceKey::KycOwner1TaxCode));
        $this->assertSame(KycControlCriterion::cases()[0]->value, $data->get(ModuleSourceKey::KycOwner1Criterion));
        $this->assertNull($data->get(ModuleSourceKey::KycOwner2Name));
        $this->assertNull($data->get(ModuleSourceKey::PraticaCodice));
        $this->assertNotNull($data->get(ModuleSourceKey::PraticaOggi));
    }

    public function test_person_sex_and_birth_date_come_from_client(): void
    {
        [$company, $kyc, $executor] = $this->companyWithKyc();

        $data = app(ModuleDataResolver::class)->resolveForClient($company, $kyc);

        $this->assertSame('M', $data->get(ModuleSourceKey::KycExecutorSex));
        $this->assertEquals($executor->fresh()->birth_date, $data->get(ModuleSourceKey::KycExecutorBirthDate));
    }

    public function test_resolve_keeps_pratica_values_and_adds_approved_kyc(): void
    {
        $client = $this->makeClient();
        KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id]);

        $pratica = new Pratica(['codice_pratica' => 'PR-1']);

        $data = app(ModuleDataResolver::class)->resolve($pratica, $client);

        $this->assertSame('PR-1', $data->get(ModuleSourceKey::PraticaCodice));
        $this->assertSame(KycPepStatus::None->value, $data->get(ModuleSourceKey::KycPepStatus));
    }
}

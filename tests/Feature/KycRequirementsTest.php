<?php

namespace Tests\Feature;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
use App\Enums\KycControlCriterion;
use App\Enums\KycEconomicActivity;
use App\Enums\KycExecutorLink;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycRiskLevel;
use App\Enums\KycWealthBand;
use App\Models\Client;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KycRequirementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeClient(bool $isPerson): Client
    {
        return Client::create([
            'name' => 'Rossi',
            'first_name' => $isPerson ? 'Mario' : null,
            'is_person' => $isPerson,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    private function filledPerson(Client $client): KycQuestionnaire
    {
        return KycQuestionnaire::factory()->create([
            'client_id' => $client->id,
            'pep_status' => KycPepStatus::None,
            'economic_activity' => KycEconomicActivity::Employee,
            'activity_sector' => KycActivitySector::Commerce,
            'activity_location' => KycActivityLocation::Region,
            'financing_nature' => KycFinancingNature::SalaryAssignment,
            'financing_purpose' => KycPersonPurpose::Personal->value,
            'income_band' => KycIncomeBand::Up100k,
            'wealth_band' => KycWealthBand::Up500k,
            'risk_level' => KycRiskLevel::Low,
        ]);
    }

    private function filledCompany(Client $client): KycQuestionnaire
    {
        $executor = $this->makeClient(true);

        return KycQuestionnaire::factory()->create([
            'client_id' => $client->id,
            'legal_nature' => KycLegalNature::SoleProprietorship,
            'geographic_area' => KycGeographicArea::Italy,
            'financing_purpose' => KycCompanyPurpose::FinancialNeeds->value,
            'executor_client_id' => $executor->id,
            'executor_link' => KycExecutorLink::LegalRepresentative,
            'executor_pep_status' => KycPepStatus::None,
            'risk_level' => KycRiskLevel::Low,
        ]);
    }

    private function addOwner(KycQuestionnaire $questionnaire, int $position, array $overrides = []): KycBeneficialOwner
    {
        return KycBeneficialOwner::create(array_merge([
            'kyc_questionnaire_id' => $questionnaire->id,
            'position' => $position,
            'client_id' => $this->makeClient(true)->id,
            'control_criterion' => KycControlCriterion::Shares,
            'pep_status' => KycPepStatus::None,
            'is_verified' => true,
        ], $overrides));
    }

    public function test_complete_person_has_no_missing_requirements(): void
    {
        $questionnaire = $this->filledPerson($this->makeClient(true));

        $this->assertSame([], $questionnaire->missingRequirements());
    }

    public function test_person_without_income_band_reports_it(): void
    {
        $questionnaire = $this->filledPerson($this->makeClient(true));
        $questionnaire->update(['income_band' => null]);

        $this->assertSame(['Reddito annuo lordo'], $questionnaire->fresh()->missingRequirements());
    }

    public function test_person_acting_for_third_party_requires_an_owner(): void
    {
        $questionnaire = $this->filledPerson($this->makeClient(true));
        $questionnaire->update(['acts_for_third_party' => true]);

        $this->assertSame(['Almeno un titolare effettivo'], $questionnaire->fresh()->missingRequirements());

        $this->addOwner($questionnaire, 1);

        $this->assertSame([], $questionnaire->fresh()->missingRequirements());
    }

    public function test_complete_company_with_verified_owner_has_no_missing_requirements(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));
        $this->addOwner($questionnaire, 1);

        $this->assertSame([], $questionnaire->fresh()->missingRequirements());
    }

    public function test_company_without_owners_requires_one(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));

        $this->assertContains('Almeno un titolare effettivo', $questionnaire->missingRequirements());
    }

    public function test_company_with_unverified_owner_requires_verification(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));
        $this->addOwner($questionnaire, 1, ['is_verified' => false]);

        $this->assertContains('Verifica di tutti i titolari effettivi', $questionnaire->fresh()->missingRequirements());
    }

    public function test_company_owner_without_pep_status_is_incomplete(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));
        $this->addOwner($questionnaire, 1, ['pep_status' => null]);

        $this->assertContains('Criterio e PEP di ogni titolare effettivo', $questionnaire->fresh()->missingRequirements());
    }

    public function test_company_with_more_than_three_owners_warns_but_is_approvable(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));
        foreach (range(1, 4) as $position) {
            $this->addOwner($questionnaire, $position);
        }
        $questionnaire = $questionnaire->fresh();

        $this->assertSame([], $questionnaire->missingRequirements());
        $this->assertStringContainsString('più di 3', $questionnaire->warnings()[0]);
    }

    public function test_warnings_are_empty_with_up_to_three_owners(): void
    {
        $questionnaire = $this->filledCompany($this->makeClient(false));
        $this->addOwner($questionnaire, 1);

        $this->assertSame([], $questionnaire->fresh()->warnings());
    }
}

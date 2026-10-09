<?php

namespace Tests\Feature;

use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
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
        $this->assertCount(11, KycActivitySector::cases());
        $this->assertCount(10, KycCompanyPurpose::cases());
        $this->assertCount(5, KycControlCriterion::cases());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
use App\Enums\KycControlCriterion;
use App\Enums\KycCoverage;
use App\Enums\KycEconomicActivity;
use App\Enums\KycExecutorLink;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\KycBeneficialOwner;
use App\Models\KycQuestionnaire;
use App\Models\PdfModule;
use App\Models\User;
use App\Services\Kyc\KycApprover;
use App\Services\Kyc\KycQavGenerator;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PdfFormFiller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class KycApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake(config('media-library.disk_name'));
    }

    private function qavModule(string $slug = KycQavGenerator::SLUG_PERSON, string $name = 'QAV Persona fisica'): PdfModule
    {
        $type = DocumentType::query()->create([
            'name' => $name,
            'slug' => $slug,
            'nature' => 'template_fillable',
            'is_monitored' => true,
            'duration' => 12,
            'duration_unit' => 'months',
        ]);

        return PdfModule::factory()->create(['name' => $name, 'document_type_id' => $type->id]);
    }

    private function makeClient(bool $isPerson): Client
    {
        return Client::create([
            'name' => 'Rossi',
            'first_name' => $isPerson ? 'Mario' : null,
            'is_person' => $isPerson,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    private function completePerson(): KycQuestionnaire
    {
        return KycQuestionnaire::factory()->create([
            'client_id' => $this->makeClient(true)->id,
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

    private function completeCompany(): KycQuestionnaire
    {
        $questionnaire = KycQuestionnaire::factory()->create([
            'client_id' => $this->makeClient(false)->id,
            'legal_nature' => KycLegalNature::SoleProprietorship,
            'geographic_area' => KycGeographicArea::Italy,
            'financing_purpose' => KycCompanyPurpose::FinancialNeeds->value,
            'executor_client_id' => $this->makeClient(true)->id,
            'executor_link' => KycExecutorLink::LegalRepresentative,
            'executor_pep_status' => KycPepStatus::None,
            'risk_level' => KycRiskLevel::Low,
        ]);

        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id,
            'position' => 1,
            'client_id' => $this->makeClient(true)->id,
            'control_criterion' => KycControlCriterion::Shares,
            'pep_status' => KycPepStatus::None,
            'is_verified' => true,
        ]);

        return $questionnaire;
    }

    private function fakeFiller(): void
    {
        $this->mock(PdfFormFiller::class)->shouldReceive('fill')->andReturn('%PDF-1.4 finto');
    }

    public function test_approve_creates_the_qav_document_and_marks_approved(): void
    {
        $this->fakeFiller();
        $module = $this->qavModule();
        $questionnaire = $this->completePerson();
        $user = User::factory()->create();

        $document = app(KycApprover::class)->approve($questionnaire, $user);

        $questionnaire->refresh();
        $this->assertSame(KycStatus::Approved, $questionnaire->status);
        $this->assertSame($user->name, $questionnaire->verified_by);
        $this->assertSame($document->id, $questionnaire->document_id);
        $this->assertSame($questionnaire->client_id, $document->documentable_id);
        $this->assertSame($module->document_type_id, $document->document_type_id);
        $this->assertTrue($document->expires_at->isSameDay(today()->addMonths(12)));
        $this->assertCount(1, $document->getMedia('documents'));
        $this->assertSame(KycCoverage::Complete, $questionnaire->client->kycCoverage());
        $this->assertSame(1, Activity::query()->where('log_name', 'kyc_qav')->count());
    }

    public function test_re_approval_is_rejected_without_a_second_document(): void
    {
        $this->fakeFiller();
        $this->qavModule();
        $questionnaire = $this->completePerson();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $document = app(KycApprover::class)->approve($questionnaire, $first);

        try {
            app(KycApprover::class)->approve($questionnaire->fresh(), $second);
            $this->fail('DomainException attesa');
        } catch (\DomainException $e) {
            $this->assertSame('KYC già approvato', $e->getMessage());
        }

        $questionnaire->refresh();
        $this->assertSame($first->name, $questionnaire->verified_by);
        $this->assertSame($document->id, $questionnaire->document_id);
        $this->assertSame(1, Document::query()->where('documentable_id', $questionnaire->client_id)->count());
        $this->assertSame(1, Activity::query()->where('log_name', 'kyc_qav')->count());
    }

    public function test_stale_instance_cannot_double_approve(): void
    {
        $this->fakeFiller();
        $this->qavModule();
        $questionnaire = $this->completePerson();
        $stale = KycQuestionnaire::find($questionnaire->id);
        app(KycApprover::class)->approve($questionnaire, User::factory()->create());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('KYC già approvato');

        app(KycApprover::class)->approve($stale, User::factory()->create());
    }

    public function test_incomplete_questionnaire_is_rejected_without_side_effects(): void
    {
        $this->fakeFiller();
        $this->qavModule();
        $questionnaire = $this->completePerson();
        $questionnaire->update(['income_band' => null]);

        try {
            app(KycApprover::class)->approve($questionnaire, User::factory()->create());
            $this->fail('DomainException attesa');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Reddito annuo lordo', $e->getMessage());
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
        $this->assertSame(0, Activity::query()->where('log_name', 'kyc_qav')->count());
    }

    public function test_owner_added_after_first_access_is_seen(): void
    {
        $this->fakeFiller();
        $this->qavModule();
        $questionnaire = $this->completePerson();
        $questionnaire->update(['acts_for_third_party' => true]);
        $questionnaire->missingRequirements();

        KycBeneficialOwner::create([
            'kyc_questionnaire_id' => $questionnaire->id,
            'position' => 1,
            'client_id' => $this->makeClient(true)->id,
            'control_criterion' => KycControlCriterion::Shares,
            'pep_status' => KycPepStatus::None,
            'is_verified' => true,
        ]);

        app(KycApprover::class)->approve($questionnaire, User::factory()->create());

        $this->assertSame(KycStatus::Approved, $questionnaire->fresh()->status);
    }

    public function test_missing_qav_module_is_rejected_without_side_effects(): void
    {
        $this->fakeFiller();
        $questionnaire = $this->completePerson();

        try {
            app(KycApprover::class)->approve($questionnaire, User::factory()->create());
            $this->fail('DomainException attesa');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Modulo QAV non configurato', $e->getMessage());
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
    }

    public function test_company_uses_the_company_module(): void
    {
        $this->fakeFiller();
        $this->qavModule();
        $company = $this->qavModule(KycQavGenerator::SLUG_COMPANY, 'QAV Persona giuridica');
        $questionnaire = $this->completeCompany();

        $document = app(KycApprover::class)->approve($questionnaire, User::factory()->create());

        $this->assertSame($company->document_type_id, $document->document_type_id);
        $this->assertSame(KycStatus::Approved, $questionnaire->fresh()->status);
    }

    public function test_filler_failure_leaves_nothing_behind(): void
    {
        $this->mock(PdfFormFiller::class)->shouldReceive('fill')->andThrow(PdfFormException::pdftkFailed('boom'));
        $this->qavModule();
        $questionnaire = $this->completePerson();

        $this->expectException(PdfFormException::class);

        try {
            app(KycApprover::class)->approve($questionnaire, User::factory()->create());
        } finally {
            $this->assertSame(0, Document::count());
            $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
            $this->assertSame(0, Activity::query()->where('log_name', 'kyc_qav')->count());
        }
    }

    public function test_pdf_is_rendered_outside_the_approval_transaction(): void
    {
        $this->qavModule();
        $questionnaire = $this->completePerson();
        $baseline = DB::connection('mysql')->transactionLevel();
        $levels = [];

        $this->mock(PdfFormFiller::class)->shouldReceive('fill')->andReturnUsing(function () use (&$levels): string {
            $levels[] = DB::connection('mysql')->transactionLevel();

            return '%PDF-1.4 finto';
        });

        app(KycApprover::class)->approve($questionnaire, User::factory()->create());

        $this->assertSame([$baseline], $levels);
    }
}

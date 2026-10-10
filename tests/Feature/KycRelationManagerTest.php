<?php

namespace Tests\Feature;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycEconomicActivity;
use App\Enums\KycFinancingNature;
use App\Enums\KycIncomeBand;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\KycQuestionnairesRelationManager;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\KycQuestionnaire;
use App\Models\PdfModule;
use App\Models\User;
use App\Services\Kyc\KycQavGenerator;
use Unico\Core\Pdf\PdfFormFiller;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KycRelationManagerTest extends TestCase
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

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    private function person(): Client
    {
        return Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function completeFor(Client $client, array $overrides = []): KycQuestionnaire
    {
        return KycQuestionnaire::factory()->create(array_merge([
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
        ], $overrides));
    }

    private function relationManager(Client $client)
    {
        return Livewire::test(KycQuestionnairesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => EditClient::class,
        ]);
    }

    public function test_lists_the_client_questionnaires(): void
    {
        $client = $this->person();
        $first = $this->completeFor($client);
        $second = $this->completeFor($client);
        $foreign = $this->completeFor($this->person());

        $this->relationManager($client)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_new_draft_is_listed_before_an_older_approved_one(): void
    {
        $client = $this->person();
        $approved = $this->completeFor($client, [
            'status' => KycStatus::Approved,
            'compiled_at' => now()->subYear(),
            'verified_at' => now()->subYear(),
        ]);
        $draft = $this->completeFor($client, ['compiled_at' => now()]);

        $this->relationManager($client)
            ->assertCanSeeTableRecords([$draft, $approved], inOrder: true);
    }

    public function test_common_pep_radio_is_visible_for_persons_and_hidden_for_companies(): void
    {
        $company = Client::create([
            'name' => 'Acme Srl',
            'is_person' => false,
            'tax_code' => strtoupper(Str::random(16)),
        ]);

        $this->relationManager($this->person())
            ->mountAction(TestAction::make('create')->table())
            ->assertFormFieldVisible('pep_status');

        $this->relationManager($company)
            ->mountAction(TestAction::make('create')->table())
            ->assertFormFieldHidden('pep_status');
    }

    public function test_beneficial_owner_select_only_offers_natural_persons(): void
    {
        $company = Client::create([
            'name' => 'Acme Srl',
            'is_person' => false,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
        $otherCompany = Client::create([
            'name' => 'ZzzBeta Spa',
            'is_person' => false,
            'tax_code' => strtoupper(Str::random(16)),
        ]);
        $person = $this->person();
        $person->update(['name' => 'ZzzAlfa']);

        $component = $this->relationManager($company)
            ->mountAction(TestAction::make('create')->table())
            ->set('mountedActions.0.data.beneficialOwners', ['item1' => []])
            ->instance();
        $schemaName = $component->getMountedActionSchemaName();
        $select = $component->{$schemaName}->getFlatComponents(withActions: false, withAbsoluteKeys: true);

        $field = collect($select)->first(fn ($component, $path) => str_ends_with((string) $path, 'client_id') && str_contains((string) $path, 'beneficialOwners'));
        $this->assertNotNull($field);
        $results = $field->getSearchResults('Zzz');

        $this->assertSame([$person->id], array_keys($results));
        $this->assertNotContains($otherCompany->id, array_keys($results));
    }

    public function test_approve_marks_the_questionnaire_approved(): void
    {
        $this->mock(PdfFormFiller::class)->shouldReceive('fill')->andReturn('%PDF-1.4 finto');
        $type = DocumentType::query()->create([
            'name' => 'QAV Persona fisica',
            'slug' => KycQavGenerator::SLUG_PERSON,
            'nature' => 'template_fillable',
            'is_monitored' => true,
            'duration' => 12,
            'duration_unit' => 'months',
        ]);
        PdfModule::factory()->create(['name' => 'QAV Persona fisica', 'document_type_id' => $type->id]);
        $client = $this->person();
        $questionnaire = $this->completeFor($client);

        $this->relationManager($client)
            ->callAction(TestAction::make('approve')->table($questionnaire))
            ->assertNotified('KYC approvato');

        $this->assertSame(KycStatus::Approved, $questionnaire->fresh()->status);
    }

    public function test_approve_of_an_incomplete_questionnaire_notifies_and_keeps_draft(): void
    {
        $client = $this->person();
        $questionnaire = $this->completeFor($client, ['income_band' => null]);

        $this->relationManager($client)
            ->callAction(TestAction::make('approve')->table($questionnaire))
            ->assertNotified('KYC incompleto');

        $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
    }

    public function test_delete_is_hidden_for_approved_questionnaires(): void
    {
        $client = $this->person();
        $approved = $this->completeFor($client, ['status' => KycStatus::Approved]);
        $draft = $this->completeFor($client);

        $this->relationManager($client)
            ->assertActionHidden(TestAction::make('delete')->table($approved))
            ->assertActionVisible(TestAction::make('delete')->table($draft));
    }

    public function test_edit_is_hidden_for_approved_questionnaires(): void
    {
        $client = $this->person();
        $approved = $this->completeFor($client, ['status' => KycStatus::Approved]);
        $draft = $this->completeFor($client);

        $this->relationManager($client)
            ->assertActionHidden(TestAction::make('edit')->table($approved))
            ->assertActionVisible(TestAction::make('edit')->table($draft));
    }

    public function test_new_compilation_keeps_the_previous_approved_one(): void
    {
        $client = $this->person();
        $approved = $this->completeFor($client, ['status' => KycStatus::Approved]);

        $this->relationManager($client)
            ->callAction(TestAction::make('create')->table(), [
                'pep_status' => KycPepStatus::None->value,
                'financing_purpose' => KycPersonPurpose::Personal->value,
                'risk_level' => KycRiskLevel::Low->value,
                'status' => KycStatus::Approved->value,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(2, $client->kycQuestionnaires()->count());
        $this->assertSame(KycStatus::Approved, $approved->fresh()->status);
        $this->assertSame(1, $client->kycQuestionnaires()->where('status', KycStatus::Draft)->count());
    }

    public function test_client_edit_page_renders_with_the_relation_manager(): void
    {
        $client = $this->person();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])->assertSuccessful();
    }
}

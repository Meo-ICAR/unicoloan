<?php

namespace Tests\Feature;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycEconomicActivity;
use App\Enums\KycFinancingNature;
use App\Enums\KycIncomeBand;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use App\Enums\UserRole;
use App\Filament\Agenti\Resources\Pratiche\Pages\CreatePraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPraticheAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\AdeguataVerificaRelationManager;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\DocumentiFirmabiliRelationManager;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\KycQuestionnaire;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AgentPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private User $agentUser;

    private Fornitore $fornitore;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->agentUser, $this->fornitore] = $this->agent('ZZ AGENTE UNO', '99900000001');
        Filament::setCurrentPanel('agenti');
        $this->actingAs($this->agentUser);
    }

    /**
     * @return array{0: User, 1: Fornitore}
     */
    private function agent(string $name, string $piva): array
    {
        $user = User::factory()->create(['role' => UserRole::AGENT->value]);
        $fornitore = Fornitore::create(['id' => (string) Str::uuid(), 'name' => $name, 'piva' => $piva, 'user_id' => $user->id]);

        return [$user, $fornitore];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pratica(array $attributes = []): Pratica
    {
        return Pratica::create($attributes + [
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'ZZ-'.Str::random(6),
            'stato_pratica' => 'INSERITA',
            'partita_iva_agente' => $this->fornitore->piva,
            'data_inserimento_pratica' => today(),
        ]);
    }

    private function documentType(bool $signed): DocumentType
    {
        return DocumentType::query()->create([
            'name' => ($signed ? 'ZZ Firmabile ' : 'ZZ Libero ').Str::random(4),
            'slug' => 'zz-'.Str::random(8),
            'nature' => 'template_fillable',
            'is_signed' => $signed,
            'is_monitored' => false,
        ]);
    }

    public function test_panel_access_is_limited_to_agents_linked_to_a_fornitore(): void
    {
        $agenti = filament()->getPanel('agenti');
        $admin = filament()->getPanel('admin');

        $this->assertTrue($this->agentUser->canAccessPanel($agenti));
        $this->assertFalse($this->agentUser->canAccessPanel($admin));

        $unlinked = User::factory()->create(['role' => UserRole::AGENT->value]);
        $this->assertFalse($unlinked->canAccessPanel($agenti));

        $employee = User::factory()->create(['role' => UserRole::USER->value]);
        $this->assertFalse($employee->canAccessPanel($agenti));
    }

    public function test_agents_cannot_open_the_admin_panel_over_http(): void
    {
        $this->get('/admin/praticas')->assertForbidden();
    }

    public function test_list_shows_only_own_open_pratiche(): void
    {
        $own = $this->pratica();
        $rejected = $this->pratica(['rejected_at' => today()]);
        $thirdParty = $this->pratica(['is_notowned' => true]);
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $foreign = $this->pratica(['partita_iva_agente' => $other->piva]);

        Livewire::test(ListPraticheAgente::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$rejected, $thirdParty, $foreign]);
    }

    public function test_default_filter_hides_pratiche_erogated_more_than_45_days_ago(): void
    {
        $notErogated = $this->pratica();
        $recent = $this->pratica(['erogated_at' => now()->subDays(45)]);
        $old = $this->pratica(['erogated_at' => now()->subDays(46)]);

        Livewire::test(ListPraticheAgente::class)
            ->assertCanSeeTableRecords([$notErogated, $recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_removing_the_default_filter_shows_older_erogated_pratiche_but_never_rejected_ones(): void
    {
        $old = $this->pratica(['erogated_at' => now()->subDays(90)]);
        $rejected = $this->pratica(['rejected_at' => today(), 'erogated_at' => now()->subDays(90)]);

        Livewire::test(ListPraticheAgente::class)
            ->removeTableFilter('recent_erogation')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$rejected]);
    }

    public function test_creating_a_pratica_assigns_it_to_the_agent_with_initial_state(): void
    {
        Clienti::create(['name' => 'ZZ BANCA', 'principal_type' => 'banca']);
        $product = Tipoprodotto::query()->whereNotNull('tipo_prodotto')->first();

        Livewire::test(CreatePraticaAgente::class)
            ->fillForm([
                'nome_cliente' => 'Mario',
                'cognome_cliente' => 'ZZ Rossi',
                'codice_fiscale' => 'ZZZMRA80A01H501A',
                'tipo_prodotto' => $product->name,
                'denominazione_banca' => 'ZZ BANCA',
                'amount' => 15000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pratica = Pratica::query()->where('cognome_cliente', 'ZZ Rossi')->sole();
        $this->assertSame('INSERITA', $pratica->stato_pratica);
        $this->assertSame($this->fornitore->piva, $pratica->partita_iva_agente);
        $this->assertSame('ZZ AGENTE UNO', $pratica->denominazione_agente);
        $this->assertStringStartsWith('AG-', $pratica->codice_pratica);
        $this->assertTrue($pratica->data_inserimento_pratica->isToday());
        $this->assertFalse((bool) $pratica->is_notowned);
    }

    public function test_creating_a_pratica_requires_the_main_fields(): void
    {
        Livewire::test(CreatePraticaAgente::class)
            ->fillForm(['nome_cliente' => null, 'cognome_cliente' => null, 'codice_fiscale' => null, 'denominazione_banca' => null, 'amount' => null])
            ->call('create')
            ->assertHasFormErrors(['nome_cliente' => 'required', 'cognome_cliente' => 'required', 'codice_fiscale' => 'required', 'denominazione_banca' => 'required', 'amount' => 'required']);
    }

    public function test_agent_cannot_open_the_pratica_of_another_agent(): void
    {
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $foreign = $this->pratica(['partita_iva_agente' => $other->piva]);

        $this->get(route('filament.agenti.resources.pratiche.view', ['record' => $foreign->getKey()]))->assertNotFound();
    }

    public function test_documents_manager_offers_only_signable_types_and_stores_the_upload(): void
    {
        $pratica = $this->pratica();
        $signable = $this->documentType(true);
        $free = $this->documentType(false);

        $component = Livewire::test(DocumentiFirmabiliRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => ViewPraticaAgente::class]);

        $component
            ->callAction(TestAction::make('create')->table(), [
                'document_type_id' => $signable->getKey(),
                'attachments' => UploadedFile::fake()->create('firmato.pdf', 20, 'application/pdf'),
            ])
            ->assertHasNoFormErrors();

        $document = Document::query()->where('documentable_id', $pratica->getKey())->sole();
        $this->assertSame($signable->getKey(), $document->document_type_id);
        $this->assertSame($signable->name, $document->name);
        $this->assertSame($this->agentUser->id, (int) $document->uploaded_by);
        $this->assertCount(1, $document->getMedia('documents'));

        $component->assertCanSeeTableRecords([$document]);
        $this->assertNotContains($free->getKey(), DocumentType::query()->where('is_signed', true)->pluck('id')->all());
    }

    public function test_non_signable_type_is_rejected_when_forced(): void
    {
        $pratica = $this->pratica();
        $free = $this->documentType(false);

        Livewire::test(DocumentiFirmabiliRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => ViewPraticaAgente::class])
            ->callAction(TestAction::make('create')->table(), [
                'document_type_id' => $free->getKey(),
                'attachments' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
            ]);

        $this->assertSame(0, Document::query()->where('documentable_id', $pratica->getKey())->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function personKycData(): array
    {
        return [
            'financing_purpose' => KycPersonPurpose::cases()[0]->value,
            'pep_status' => KycPepStatus::cases()[0]->value,
            'economic_activity' => KycEconomicActivity::cases()[0]->value,
            'activity_sector' => KycActivitySector::cases()[0]->value,
            'activity_location' => KycActivityLocation::cases()[0]->value,
            'financing_nature' => KycFinancingNature::cases()[0]->value,
            'income_band' => KycIncomeBand::cases()[0]->value,
            'wealth_band' => KycWealthBand::cases()[0]->value,
        ];
    }

    private function kycManager(Pratica $pratica): Testable
    {
        return Livewire::test(AdeguataVerificaRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => ViewPraticaAgente::class]);
    }

    public function test_kyc_cannot_start_without_the_client_in_the_registry(): void
    {
        $pratica = $this->pratica(['codice_fiscale' => 'ZZNOCLIENT80A01H501X']);

        $this->kycManager($pratica)->assertActionHidden(TestAction::make('create')->table());
    }

    public function test_producer_fills_the_kyc_as_draft_and_submits_it_to_the_istruttoria(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501A']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);

        $manager = $this->kycManager($pratica);
        $manager->callAction(TestAction::make('create')->table(), $this->personKycData())->assertHasNoFormErrors();

        $questionnaire = KycQuestionnaire::query()->where('pratica_id', $pratica->getKey())->sole();
        $this->assertSame($client->getKey(), $questionnaire->client_id);
        $this->assertSame(KycStatus::Draft, $questionnaire->status);
        $this->assertNull($questionnaire->risk_level);

        $manager->assertActionHidden(TestAction::make('create')->table());

        $manager->callAction(TestAction::make('submit')->table($questionnaire))->assertNotified('Inviata all\'istruttoria');
        $this->assertSame(KycStatus::Complete, $questionnaire->fresh()->status);
        $manager->assertActionHidden(TestAction::make('submit')->table($questionnaire))
            ->assertActionHidden(TestAction::make('edit')->table($questionnaire));
    }

    public function test_incomplete_kyc_is_not_submitted(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501B']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $questionnaire = KycQuestionnaire::factory()->create(['client_id' => $client->id, 'pratica_id' => $pratica->getKey()]);

        $this->kycManager($pratica)
            ->callAction(TestAction::make('submit')->table($questionnaire))
            ->assertNotified('Compilazione incompleta');

        $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
    }

    public function test_approved_kyc_is_read_only_for_the_producer(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501C']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $approved = KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id, 'pratica_id' => $pratica->getKey()]);

        $this->kycManager($pratica)
            ->assertActionHidden(TestAction::make('edit')->table($approved))
            ->assertActionHidden(TestAction::make('submit')->table($approved));
    }
}

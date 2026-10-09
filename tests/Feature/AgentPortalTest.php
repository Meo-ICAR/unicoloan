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
use App\Enums\SignatureRequestStatus;
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
use App\Models\PdfModule;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\SignatureRequest;
use App\Models\Tipoprodotto;
use App\Models\User;
use App\Services\Kyc\KycQavGenerator;
use App\Services\PdfFormFiller;
use App\Services\Signature\FakeSignatureProvider;
use App\Services\Signature\SignatureRequestService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_agents_are_denied_every_plan_feature_and_cannot_download_documents(): void
    {
        $this->assertFalse(checkPiano('documents'));
        $this->assertFalse(checkPiano('firma'));

        $document = $this->fornitore->documents()->create(['name' => 'ZZ doc', 'status' => 'caricato', 'spatie_collection' => 'documents']);

        $this->get(route('documents.download', $document))->assertForbidden();
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
                'client_email' => 'cliente@example.test',
                'client_phone' => '+393331234567',
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

        $client = Client::query()->where('tax_code', 'ZZZMRA80A01H501A')->sole();
        $this->assertTrue((bool) $client->is_person);
        $this->assertSame('ZZ Rossi', $client->name);
        $this->assertSame('Mario', $client->first_name);
        $this->assertSame('cliente@example.test', $client->email);
        $this->assertSame('+393331234567', $client->phone);
    }

    public function test_creating_a_pratica_reuses_an_existing_client_without_changing_it(): void
    {
        Clienti::create(['name' => 'ZZ BANCA', 'principal_type' => 'banca']);
        $product = Tipoprodotto::query()->whereNotNull('tipo_prodotto')->first();
        $existing = Client::create(['name' => 'ZZ Rossi', 'is_person' => true, 'tax_code' => 'ZZZMRA80A01H501A', 'email' => 'originale@example.test']);
        $this->pratica(['codice_fiscale' => 'ZZZMRA80A01H501A']);

        Livewire::test(CreatePraticaAgente::class)
            ->fillForm([
                'nome_cliente' => 'Mario', 'cognome_cliente' => 'ZZ Rossi', 'codice_fiscale' => 'zzzmra80a01h501a',
                'client_email' => 'altra@example.test', 'client_phone' => '+393331234567',
                'tipo_prodotto' => $product->name, 'denominazione_banca' => 'ZZ BANCA', 'amount' => 1000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Client::query()->where('tax_code', 'ZZZMRA80A01H501A')->count());
        $this->assertSame('originale@example.test', $existing->fresh()->email);
    }

    public function test_creating_a_pratica_for_a_client_of_another_agent_is_blocked(): void
    {
        Clienti::create(['name' => 'ZZ BANCA', 'principal_type' => 'banca']);
        $product = Tipoprodotto::query()->whereNotNull('tipo_prodotto')->first();
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $foreign = Client::create(['name' => 'ZZ Altrui', 'is_person' => true, 'tax_code' => 'ZZZALT80A01H501A']);
        $this->pratica(['codice_fiscale' => 'ZZZALT80A01H501A', 'partita_iva_agente' => $other->piva]);
        $orphan = Client::create(['name' => 'ZZ Senza pratiche', 'is_person' => true, 'tax_code' => 'ZZZORF80A01H501A']);

        foreach (['ZZZALT80A01H501A', 'ZZZORF80A01H501A'] as $code) {
            Livewire::test(CreatePraticaAgente::class)
                ->fillForm([
                    'nome_cliente' => 'Mario', 'cognome_cliente' => 'ZZ Altrui', 'codice_fiscale' => $code,
                    'client_email' => 'x@example.test', 'client_phone' => '+393331234567',
                    'tipo_prodotto' => $product->name, 'denominazione_banca' => 'ZZ BANCA', 'amount' => 1000,
                ])
                ->call('create')
                ->assertHasFormErrors(['codice_fiscale']);
        }

        $this->assertSame(0, Pratica::query()->where('cognome_cliente', 'ZZ Altrui')->count());
        $this->assertNotNull($foreign->fresh());
        $this->assertNotNull($orphan->fresh());
    }

    public function test_kyc_cannot_start_on_a_client_shared_with_other_agents(): void
    {
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $client = Client::create(['name' => 'ZZ Condiviso', 'is_person' => true, 'tax_code' => 'ZZZCON80A01H501A']);
        $own = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $this->pratica(['codice_fiscale' => $client->tax_code, 'partita_iva_agente' => $other->piva]);

        $this->kycManager($own)->assertActionHidden(TestAction::make('create')->table());
    }

    public function test_an_agent_without_vat_number_sees_no_pratiche(): void
    {
        $this->fornitore->forceFill(['piva' => null])->save();
        $this->pratica(['partita_iva_agente' => '']);
        $this->pratica(['partita_iva_agente' => null]);

        Livewire::test(ListPraticheAgente::class)->assertCountTableRecords(0);
    }

    public function test_creating_a_company_pratica_stores_the_vat_number(): void
    {
        Clienti::create(['name' => 'ZZ BANCA', 'principal_type' => 'banca']);
        $product = Tipoprodotto::query()->whereNotNull('tipo_prodotto')->first();

        Livewire::test(CreatePraticaAgente::class)
            ->fillForm([
                'client_type' => 'company', 'cognome_cliente' => 'ZZ Srl', 'codice_fiscale' => '99988877766',
                'client_email' => 'srl@example.test', 'client_phone' => '+393331234567',
                'tipo_prodotto' => $product->name, 'denominazione_banca' => 'ZZ BANCA', 'amount' => 1000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::query()->where('vat_number', '99988877766')->sole();
        $this->assertFalse((bool) $client->is_person);
        $this->assertTrue((bool) $client->is_company);
        $this->assertSame('ZZ Srl', $client->name);
        $this->assertNull(Pratica::query()->where('cognome_cliente', 'ZZ Srl')->value('nome_cliente'));
    }

    public function test_creating_a_pratica_rejects_invalid_tax_codes(): void
    {
        Livewire::test(CreatePraticaAgente::class)
            ->fillForm(['codice_fiscale' => 'CORTO', 'client_email' => 'x@example.test', 'client_phone' => '+393331234567'])
            ->call('create')
            ->assertHasFormErrors(['codice_fiscale']);
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
            'risk_level' => KycRiskLevel::Low->value,
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

    private function setUpQav(): void
    {
        Storage::fake('public');
        Storage::fake(config('media-library.disk_name'));
        config(['signature.driver' => 'fake']);

        $pdf = (string) file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf'));
        $filler = $this->mock(PdfFormFiller::class);
        $filler->shouldReceive('fill')->andReturn($pdf);
        $filler->shouldReceive('flattenContent')->andReturnArg(0);

        $type = DocumentType::query()->create([
            'name' => 'QAV Persona fisica', 'slug' => KycQavGenerator::SLUG_PERSON, 'nature' => 'template_fillable',
            'is_monitored' => true, 'duration' => 12, 'duration_unit' => 'months', 'is_signed' => true,
        ]);
        PdfModule::factory()->create([
            'name' => 'QAV Persona fisica', 'document_type_id' => $type->id,
            'signature_slots' => [['slot' => 'signer1', 'role' => 'client', 'page' => 1, 'x' => 50, 'y' => 700, 'width' => 142, 'height' => 40]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function signerData(): array
    {
        return ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario@example.test', 'phone' => '338 1234567'];
    }

    public function test_producer_fills_the_kyc_as_a_draft_with_the_risk_level(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501A']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);

        $manager = $this->kycManager($pratica);
        $manager->callAction(TestAction::make('create')->table(), $this->personKycData())->assertHasNoFormErrors();

        $questionnaire = KycQuestionnaire::query()->where('pratica_id', $pratica->getKey())->sole();
        $this->assertSame($client->getKey(), $questionnaire->client_id);
        $this->assertSame(KycStatus::Draft, $questionnaire->status);
        $this->assertSame(KycRiskLevel::Low, $questionnaire->risk_level);
        $this->assertNull($questionnaire->document_id);

        $manager->assertActionHidden(TestAction::make('create')->table());
    }

    public function test_kyc_is_sent_to_the_client_for_otp_signature_and_approved_when_signed(): void
    {
        $this->setUpQav();
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501B']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $questionnaire = KycQuestionnaire::factory()->create(['client_id' => $client->id, 'pratica_id' => $pratica->getKey()] + $this->personKycData() + ['risk_level' => KycRiskLevel::Low->value]);

        $manager = $this->kycManager($pratica);
        $manager->callAction(TestAction::make('sendForSignature')->table($questionnaire), $this->signerData())->assertNotified('Richiesta di firma inviata al cliente');

        $questionnaire->refresh();
        $this->assertSame(KycStatus::Complete, $questionnaire->status);
        $document = Document::query()->findOrFail($questionnaire->document_id);
        $request = $document->latestSignatureRequest();
        $this->assertSame(SignatureRequestStatus::Sent, $request->status);
        $this->assertSame('+393381234567', $request->signers->sole()->phone);
        $this->assertSame($this->agentUser->id, (int) $request->requested_by);

        $manager->assertActionHidden(TestAction::make('sendForSignature')->table($questionnaire))
            ->assertActionHidden(TestAction::make('edit')->table($questionnaire));

        (new FakeSignatureProvider)->completeAll($request->provider_ref);
        $manager->callAction(TestAction::make('refreshSignature')->table($questionnaire->fresh()))->assertNotified('Stato firma: Firmata');

        $questionnaire->refresh();
        $signed = Document::query()->findOrFail($questionnaire->document_id);
        $this->assertNotSame($document->getKey(), $signed->getKey());
        $this->assertTrue((bool) $signed->is_signed);
        $this->assertSame(KycStatus::Approved, $questionnaire->status);
        $this->assertSame($this->agentUser->name, $questionnaire->verified_by);
        $this->assertTrue(Document::withTrashed()->findOrFail($document->getKey())->trashed());
    }

    public function test_incomplete_kyc_is_not_sent_for_signature(): void
    {
        $this->setUpQav();
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501D']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $questionnaire = KycQuestionnaire::factory()->create(['client_id' => $client->id, 'pratica_id' => $pratica->getKey()]);

        $this->kycManager($pratica)
            ->callAction(TestAction::make('sendForSignature')->table($questionnaire), $this->signerData())
            ->assertNotified('Compilazione incompleta');

        $this->assertSame(KycStatus::Draft, $questionnaire->fresh()->status);
        $this->assertNull($questionnaire->fresh()->document_id);
        $this->assertSame(0, SignatureRequest::query()->count());
    }

    public function test_kyc_already_approved_by_the_istruttoria_keeps_its_status_when_signed(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501E']);
        $questionnaire = KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id]);
        $document = $client->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);
        $questionnaire->update(['document_id' => $document->getKey()]);
        $request = SignatureRequest::create(['document_id' => $document->getKey(), 'provider' => 'fake', 'status' => SignatureRequestStatus::Sent, 'requested_by' => $this->agentUser->id]);

        $reflection = new \ReflectionMethod(SignatureRequestService::class, 'approveSignedKyc');
        $signed = Document::query()->create(['name' => 'QAV firmato', 'status' => 'caricato', 'spatie_collection' => 'documents', 'documentable_type' => $document->documentable_type, 'documentable_id' => $document->documentable_id]);
        $reflection->invoke(app(SignatureRequestService::class), $request, $document, $signed, now());

        $questionnaire->refresh();
        $this->assertSame($signed->getKey(), $questionnaire->document_id);
        $this->assertSame('Tester', $questionnaire->verified_by);
    }

    public function test_approved_kyc_is_read_only_for_the_producer(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTKYC80A01H501C']);
        $pratica = $this->pratica(['codice_fiscale' => $client->tax_code]);
        $approved = KycQuestionnaire::factory()->approved()->create(['client_id' => $client->id, 'pratica_id' => $pratica->getKey()]);

        $this->kycManager($pratica)
            ->assertActionHidden(TestAction::make('edit')->table($approved))
            ->assertActionHidden(TestAction::make('sendForSignature')->table($approved));
    }
}

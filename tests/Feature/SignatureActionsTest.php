<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\SignatureRequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\KycQuestionnairesRelationManager;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\KycQuestionnaire;
use App\Models\PdfModule;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\FakeSignatureProvider;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerDefaults;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class SignatureActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake(config('media-library.disk_name'));
        config(['signature.driver' => 'fake']);
        Filament::setCurrentPanel('admin');
        $this->user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
            'email' => 'mario@example.test',
            'phone' => '338 1234567',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $slots
     */
    private function document(?array $slots = null, ?Client $owner = null): Document
    {
        $slots ??= [
            ['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 50.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
        ];

        $type = DocumentType::query()->create([
            'name' => 'QAV Persona fisica',
            'slug' => 'qav-'.Str::random(6),
            'nature' => 'template_fillable',
            'is_monitored' => true,
            'duration' => 12,
            'duration_unit' => 'months',
        ]);
        PdfModule::factory()->create(['name' => 'QAV', 'document_type_id' => $type->id, 'signature_slots' => $slots]);

        $document = ($owner ?? $this->client)->documents()->create([
            'name' => 'QAV Rossi',
            'status' => 'caricato',
            'spatie_collection' => 'documents',
            'document_type_id' => $type->id,
        ]);
        $document->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')))
            ->usingFileName('qav.pdf')
            ->toMediaCollection('documents');

        return $document->fresh();
    }

    private function manager(): Testable
    {
        return Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $this->client,
            'pageClass' => EditClient::class,
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function clientData(): array
    {
        return ['cliente' => [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario@example.test',
            'phone' => '338 1234567',
            'tax_code' => $this->client->tax_code,
        ]];
    }

    private function requestFor(Document $document, SignatureRequestStatus $status): SignatureRequest
    {
        return SignatureRequest::factory()->create([
            'document_id' => $document->getKey(),
            'status' => $status,
        ]);
    }

    public function test_send_action_is_visible_for_a_signable_document(): void
    {
        $document = $this->document();

        $this->manager()->assertActionVisible(TestAction::make('sendForSignature')->table($document));
    }

    public function test_send_action_is_hidden_without_signature_slots(): void
    {
        $document = $this->document([]);

        $this->manager()->assertActionHidden(TestAction::make('sendForSignature')->table($document));
    }

    public function test_send_action_is_hidden_for_a_signed_document(): void
    {
        $document = $this->document();
        $document->forceFill(['is_signed' => true])->save();

        $this->manager()->assertActionHidden(TestAction::make('sendForSignature')->table($document));
    }

    public function test_send_action_is_hidden_with_an_open_request_and_back_after_a_final_one(): void
    {
        $document = $this->document();
        $request = $this->requestFor($document, SignatureRequestStatus::Sent);

        $this->manager()->assertActionHidden(TestAction::make('sendForSignature')->table($document));

        $request->forceFill(['status' => SignatureRequestStatus::Declined])->save();

        $this->manager()->assertActionVisible(TestAction::make('sendForSignature')->table($document));
    }

    public function test_modal_is_prefilled_with_the_client_defaults(): void
    {
        $document = $this->document();

        $this->manager()
            ->mountAction(TestAction::make('sendForSignature')->table($document))
            ->assertSchemaStateSet(['cliente' => [
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'email' => 'mario@example.test',
                'phone' => '338 1234567',
                'tax_code' => $this->client->tax_code,
            ]]);
    }

    public function test_sending_creates_a_sent_request_with_the_edited_data(): void
    {
        $document = $this->document();
        $data = $this->clientData();
        $data['cliente']['email'] = 'altra@example.test';

        $this->manager()
            ->callAction(TestAction::make('sendForSignature')->table($document), $data)
            ->assertNotified('Richiesta di firma inviata');

        $request = $document->latestSignatureRequest();
        $this->assertSame(SignatureRequestStatus::Sent, $request->status);
        $signer = $request->signers()->first();
        $this->assertSame('altra@example.test', $signer->email);
        $this->assertSame('manual', $signer->signer_type);
        $this->assertSame($this->user->getKey(), (int) $request->requested_by);
    }

    public function test_sending_unedited_defaults_keeps_the_client_signer_type(): void
    {
        $document = $this->document();

        $this->manager()
            ->callAction(TestAction::make('sendForSignature')->table($document), $this->clientData());

        $this->assertSame('client', $document->latestSignatureRequest()->signers()->first()->signer_type);
    }

    public function test_missing_required_phone_blocks_the_send(): void
    {
        $document = $this->document();
        $data = $this->clientData();
        $data['cliente']['phone'] = '';

        $this->manager()
            ->callAction(TestAction::make('sendForSignature')->table($document), $data)
            ->assertHasActionErrors(['cliente.phone' => 'required']);

        $this->assertSame(0, SignatureRequest::query()->count());
    }

    public function test_service_errors_become_a_danger_notification_and_no_request(): void
    {
        $document = $this->document();
        $this->mock(SignatureRequestService::class)
            ->shouldReceive('send')
            ->andThrow(new SignatureRequestException('Telefono non valido per Mario Rossi.'));

        $this->manager()
            ->callAction(TestAction::make('sendForSignature')->table($document), $this->clientData())
            ->assertNotified('Invio per la firma non riuscito');

        $this->assertSame(0, SignatureRequest::query()->count());
    }

    public function test_refresh_marks_the_document_signed_after_completion(): void
    {
        $document = $this->document();
        $request = app(SignatureRequestService::class)->send($document, [
            SignerDefaults::for($document)['cliente'],
        ], $this->user);
        (new FakeSignatureProvider)->completeAll($request->provider_ref);

        $this->manager()
            ->assertActionVisible(TestAction::make('refreshSignature')->table($document))
            ->callAction(TestAction::make('refreshSignature')->table($document))
            ->assertNotified('Documento firmato');

        $this->assertTrue($document->fresh()->is_signed);
        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
    }

    public function test_refresh_while_still_waiting_notifies_info(): void
    {
        $document = $this->document();
        app(SignatureRequestService::class)->send($document, [
            SignerDefaults::for($document)['cliente'],
        ], $this->user);

        $this->manager()
            ->callAction(TestAction::make('refreshSignature')->table($document))
            ->assertNotified('Ancora in attesa di firma');
    }

    public function test_refresh_and_cancel_are_hidden_without_an_open_request(): void
    {
        $document = $this->document();

        $this->manager()
            ->assertActionHidden(TestAction::make('refreshSignature')->table($document))
            ->assertActionHidden(TestAction::make('cancelSignature')->table($document));
    }

    public function test_cancel_moves_the_request_to_cancelled(): void
    {
        $document = $this->document();
        $request = app(SignatureRequestService::class)->send($document, [
            SignerDefaults::for($document)['cliente'],
        ], $this->user);

        $this->manager()
            ->callAction(TestAction::make('cancelSignature')->table($document))
            ->assertNotified('Richiesta di firma annullata');

        $this->assertSame(SignatureRequestStatus::Cancelled, $request->fresh()->status);
    }

    public function test_download_signed_is_visible_only_when_signed_with_media(): void
    {
        $document = $this->document();

        $this->manager()->assertActionHidden(TestAction::make('downloadSigned')->table($document));

        $document->forceFill(['is_signed' => true])->save();
        $this->manager()->assertActionHidden(TestAction::make('downloadSigned')->table($document));

        $document->addMediaFromString('%PDF-signed')->usingFileName('firmato.pdf')->toMediaCollection('signed');
        $this->manager()
            ->assertActionVisible(TestAction::make('downloadSigned')->table($document))
            ->callAction(TestAction::make('downloadSigned')->table($document))
            ->assertFileDownloaded('firmato.pdf');
    }

    public function test_signature_state_column_shows_each_state(): void
    {
        $unsigned = $this->document();
        $waiting = $this->document();
        $this->requestFor($waiting, SignatureRequestStatus::Sent);
        $signed = $this->document();
        $signed->forceFill(['is_signed' => true])->save();
        $declined = $this->document();
        $this->requestFor($declined, SignatureRequestStatus::Declined);
        $expired = $this->document();
        $this->requestFor($expired, SignatureRequestStatus::Expired);

        $this->manager()
            ->assertTableColumnStateSet('signature_state', 'Non firmato', $unsigned)
            ->assertTableColumnStateSet('signature_state', 'In attesa di firma', $waiting)
            ->assertTableColumnStateSet('signature_state', 'Firmato', $signed)
            ->assertTableColumnStateSet('signature_state', 'Rifiutata', $declined)
            ->assertTableColumnStateSet('signature_state', 'Scaduta', $expired);
    }

    public function test_kyc_manager_shows_the_action_for_an_approved_questionnaire_with_a_signable_document(): void
    {
        $document = $this->document();
        $approved = KycQuestionnaire::factory()->create([
            'client_id' => $this->client->id,
            'status' => KycStatus::Approved,
            'document_id' => $document->getKey(),
        ]);
        $draft = KycQuestionnaire::factory()->create([
            'client_id' => $this->client->id,
            'status' => KycStatus::Draft,
            'document_id' => $document->getKey(),
        ]);
        $withoutDocument = KycQuestionnaire::factory()->create([
            'client_id' => $this->client->id,
            'status' => KycStatus::Approved,
        ]);

        Livewire::test(KycQuestionnairesRelationManager::class, [
            'ownerRecord' => $this->client,
            'pageClass' => EditClient::class,
        ])
            ->assertActionVisible(TestAction::make('sendForSignature')->table($approved))
            ->assertActionHidden(TestAction::make('sendForSignature')->table($draft))
            ->assertActionHidden(TestAction::make('sendForSignature')->table($withoutDocument));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\SignatureRequestStatus;
use App\Enums\SignerRole;
use App\Enums\SignerStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\Dto\EnvelopeData;
use App\Services\Signature\Dto\EnvelopeRef;
use App\Services\Signature\Exceptions\ProviderUnavailableException;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\SignatureProvider;
use App\Services\Signature\SignatureProviderManager;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerDefaults;
use App\Services\Signature\SignerInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SignatureSendTest extends TestCase
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
        config(['signature.driver' => 'fake']);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $slots
     */
    private function documentWithModule(?array $slots = null, bool $withPdf = true): Document
    {
        $slots ??= [
            ['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 50.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
            ['slot' => 'collaboratore', 'role' => 'collaborator', 'page' => 1, 'x' => 300.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
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

        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16))]);
        $document = $client->documents()->create([
            'name' => 'QAV Rossi',
            'status' => 'caricato',
            'spatie_collection' => 'documents',
            'document_type_id' => $type->id,
        ]);

        if ($withPdf) {
            $document->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')))
                ->usingFileName('qav.pdf')
                ->toMediaCollection('documents');
        }

        return $document->fresh();
    }

    /**
     * @return array<int, SignerInput>
     */
    private function signers(?string $clientPhone = '338 1234567'): array
    {
        return [
            new SignerInput('cliente', SignerRole::Client, 'Mario', 'Rossi', 'mario@example.test', $clientPhone, 'RSSMRA80A01H501U', 'client', '1'),
            new SignerInput('collaboratore', SignerRole::Collaborator, 'Luca', 'Verdi', 'luca@example.test', '+393331112233', null, 'agent', '2'),
        ];
    }

    private function service(): SignatureRequestService
    {
        return app(SignatureRequestService::class);
    }

    private function mockProvider(): MockInterface
    {
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('name')->andReturn('fake');
        $provider->shouldReceive('requiredContactFields')->andReturn(['email', 'phone']);

        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        return $provider;
    }

    private function assertRejected(Document $document, array $signers, string $message): void
    {
        try {
            $this->service()->send($document, $signers, User::factory()->create());
            $this->fail('Attesa SignatureRequestException');
        } catch (SignatureRequestException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_send_succeeds_and_records_everything(): void
    {
        $document = $this->documentWithModule();
        $user = User::factory()->create();

        $request = $this->service()->send($document, $this->signers(), $user);

        $request = $request->fresh();
        $this->assertSame(SignatureRequestStatus::Sent, $request->status);
        $this->assertNotEmpty($request->provider_ref);
        $this->assertSame('fake', $request->provider);
        $this->assertNotNull($request->sent_at);
        $this->assertSame($user->id, (int) $request->requested_by);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $request->expires_at->timestamp, 60);

        $signers = $request->signers;
        $this->assertSame(['cliente', 'collaboratore'], $signers->pluck('slot')->all());
        $this->assertSame([1, 2], $signers->pluck('position')->all());
        $this->assertTrue($signers->every(fn ($s) => $s->status === SignerStatus::Notified && filled($s->provider_signer_ref)));
        $this->assertSame('+393381234567', $signers->first()->phone);

        $activity = Activity::query()->where('event', 'firma_inviata')->sole();
        $this->assertSame($request->id, (int) $activity->subject_id);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertEqualsCanonicalizing(['signature_request_id', 'provider'], array_keys($activity->properties->all()));
        $this->assertStringNotContainsString('example.test', $activity->properties->toJson());
        $this->assertStringNotContainsString('+39', $activity->properties->toJson());
    }

    public function test_document_without_pdf_is_rejected(): void
    {
        $this->assertRejected($this->documentWithModule(withPdf: false), $this->signers(), 'PDF');
        $this->assertSame(0, SignatureRequest::count());
    }

    public function test_module_without_slots_is_rejected(): void
    {
        $document = $this->documentWithModule([]);

        $this->assertRejected($document, $this->signers(), 'firma');
        $this->assertSame(0, SignatureRequest::count());
    }

    public function test_slots_must_match_the_module_exactly(): void
    {
        $document = $this->documentWithModule();

        $this->assertRejected($document, [$this->signers()[0]], 'firmatari');

        $extra = [...$this->signers(), new SignerInput('altro', SignerRole::Client, 'A', 'B', 'a@example.test', '+393331112244', null, null, null)];
        $this->assertRejected($document, $extra, 'firmatari');

        $wrong = $this->signers();
        $wrong[1] = new SignerInput('sconosciuto', SignerRole::Collaborator, 'Luca', 'Verdi', 'l@example.test', '+393331112233', null, null, null);
        $this->assertRejected($document, $wrong, 'firmatari');
    }

    public function test_open_request_blocks_a_new_one_until_closed(): void
    {
        $document = $this->documentWithModule();
        $first = $this->service()->send($document, $this->signers(), User::factory()->create());

        $this->assertRejected($document, $this->signers(), 'già');

        $first->update(['status' => SignatureRequestStatus::Declined]);

        $second = $this->service()->send($document, $this->signers(), User::factory()->create());
        $this->assertSame(SignatureRequestStatus::Sent, $second->fresh()->status);
    }

    public function test_missing_phone_is_rejected_before_calling_the_provider(): void
    {
        $document = $this->documentWithModule();
        $provider = $this->mockProvider();
        $provider->shouldNotReceive('createEnvelope');

        $this->assertRejected($document, $this->signers(null), 'Telefono');
        $this->assertSame(0, SignatureRequest::count());
    }

    public function test_missing_name_or_email_is_rejected(): void
    {
        $document = $this->documentWithModule();
        $provider = $this->mockProvider();
        $provider->shouldNotReceive('createEnvelope');

        $noName = $this->signers();
        $noName[0] = new SignerInput('cliente', SignerRole::Client, '', 'Rossi', 'm@example.test', '+393381234567', null, null, null);
        $this->assertRejected($document, $noName, 'nome');

        $noEmail = $this->signers();
        $noEmail[1] = new SignerInput('collaboratore', SignerRole::Collaborator, 'Luca', 'Verdi', null, '+393331112233', null, null, null);
        $this->assertRejected($document, $noEmail, 'Email');
    }

    public function test_invalid_phone_is_rejected_with_the_signer_name(): void
    {
        $document = $this->documentWithModule();
        $provider = $this->mockProvider();
        $provider->shouldNotReceive('createEnvelope');

        $this->assertRejected($document, $this->signers('abc'), 'Telefono non valido per Mario Rossi');
    }

    public function test_phone_is_normalized_in_the_data_given_to_the_provider(): void
    {
        $document = $this->documentWithModule();
        $provider = $this->mockProvider();
        $provider->shouldReceive('createEnvelope')
            ->once()
            ->with(Mockery::on(function (EnvelopeData $data): bool {
                return $data->signers[0]->phone === '+393381234567'
                    && $data->signers[0]->position === 1
                    && $data->signers[1]->position === 2
                    && $data->signers[0]->placement->page === 1
                    && $data->name === 'QAV Rossi'
                    && $data->fileName === 'qav-rossi.pdf'
                    && str_starts_with($data->pdf, '%PDF')
                    && $data->externalId === (string) SignatureRequest::query()->latest('id')->value('id');
            }))
            ->andReturn(new EnvelopeRef('ref-1', ['cliente' => 's1', 'collaboratore' => 's2']));

        $request = $this->service()->send($document, $this->signers(), User::factory()->create());

        $this->assertSame('ref-1', $request->fresh()->provider_ref);
        $this->assertSame(['s1', 's2'], $request->signers->pluck('provider_signer_ref')->all());
    }

    public function test_provider_failure_marks_the_request_failed_and_allows_a_retry(): void
    {
        $document = $this->documentWithModule();
        $provider = $this->mockProvider();
        $provider->shouldReceive('createEnvelope')
            ->once()
            ->andThrow(new ProviderUnavailableException('Servizio non disponibile per mario@example.test +393381234567'));

        try {
            $this->service()->send($document, $this->signers(), User::factory()->create());
            $this->fail('Attesa SignatureRequestException');
        } catch (SignatureRequestException) {
        }

        $request = SignatureRequest::query()->sole();
        $this->assertSame(SignatureRequestStatus::Failed, $request->status);
        $this->assertNull($request->provider_ref);
        $this->assertNotEmpty($request->failure_reason);
        $this->assertLessThanOrEqual(200, mb_strlen($request->failure_reason));
        $this->assertStringNotContainsString('mario@example.test', $request->failure_reason);
        $this->assertStringNotContainsString('3381234567', $request->failure_reason);
        $this->assertSame(0, Activity::query()->where('event', 'firma_inviata')->count());

        $provider->shouldReceive('createEnvelope')->once()->andReturn(new EnvelopeRef('ref-2', ['cliente' => 's1', 'collaboratore' => 's2']));
        $retry = $this->service()->send($document, $this->signers(), User::factory()->create());

        $this->assertSame(SignatureRequestStatus::Sent, $retry->fresh()->status);
    }

    public function test_create_envelope_is_called_outside_any_transaction(): void
    {
        $document = $this->documentWithModule();
        $baseline = DB::connection('mysql')->transactionLevel();
        $levels = [];
        $provider = $this->mockProvider();
        $provider->shouldReceive('createEnvelope')->once()->andReturnUsing(function () use (&$levels): EnvelopeRef {
            $levels[] = DB::connection('mysql')->transactionLevel();

            return new EnvelopeRef('ref-3', ['cliente' => 's1', 'collaboratore' => 's2']);
        });

        $this->service()->send($document, $this->signers(), User::factory()->create());

        $this->assertSame([$baseline], $levels);
    }

    public function test_defaults_for_a_person_client_prefill_the_client_slot(): void
    {
        $client = Client::create([
            'name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16)),
            'email' => 'mario@example.test', 'phone' => '338 1234567',
        ]);
        $document = $client->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);

        $defaults = SignerDefaults::for($document);

        $this->assertSame(['cliente'], array_keys($defaults));
        $this->assertSame(SignerRole::Client, $defaults['cliente']->role);
        $this->assertSame('Mario', $defaults['cliente']->firstName);
        $this->assertSame('Rossi', $defaults['cliente']->lastName);
        $this->assertSame('mario@example.test', $defaults['cliente']->email);
        $this->assertSame($client->tax_code, $defaults['cliente']->taxCode);
        $this->assertSame('client', $defaults['cliente']->signerType);
    }

    public function test_defaults_for_a_company_use_the_legal_representative(): void
    {
        $rep = Client::create([
            'name' => 'Bianchi', 'first_name' => 'Anna', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16)),
            'email' => 'anna@example.test', 'phone' => '+393401234567',
        ]);
        $company = Client::create([
            'name' => 'Acme Srl', 'is_person' => false, 'tax_code' => strtoupper(Str::random(16)),
            'email' => 'info@example.test', 'legal_representative_id' => $rep->id,
        ]);
        $document = $company->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);

        $defaults = SignerDefaults::for($document);

        $this->assertSame('Anna', $defaults['cliente']->firstName);
        $this->assertSame('Bianchi', $defaults['cliente']->lastName);
        $this->assertSame('anna@example.test', $defaults['cliente']->email);
    }

    public function test_signer_input_splits_agent_and_user_names(): void
    {
        $user = User::factory()->create(['name' => 'Giulia Maria Neri', 'email' => 'giulia@example.test']);

        $input = SignerInput::fromUser($user, 'collaboratore');

        $this->assertSame('Giulia', $input->firstName);
        $this->assertSame('Maria Neri', $input->lastName);
        $this->assertNull($input->phone);
        $this->assertSame('user', $input->signerType);
    }
}

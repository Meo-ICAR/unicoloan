<?php

namespace Tests\Feature;

use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Enums\SignerRole;
use Unico\Core\Enums\SignerStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSchedule;
use App\Models\DocumentType;
use App\Models\KycQuestionnaire;
use App\Models\PdfModule;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\Dto\EnvelopeStatus;
use App\Services\Signature\Dto\SignatureEvent;
use App\Services\Signature\Dto\SignerState;
use App\Services\Signature\Exceptions\ProviderUnavailableException;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\FakeSignatureProvider;
use App\Services\Signature\SignatureProvider;
use App\Services\Signature\SignatureProviderManager;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SignatureReconcileTest extends TestCase
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

    private function fake(): FakeSignatureProvider
    {
        return new FakeSignatureProvider;
    }

    private function sentRequest(?Document $document = null): SignatureRequest
    {
        return $this->service()->send($document ?? $this->documentWithModule(), $this->signers(), User::factory()->create())->fresh();
    }

    private function signedDocument(Document $document): Document
    {
        $renewedById = Document::withTrashed()->findOrFail($document->getKey())->replaced_by_id;

        return Document::query()->findOrFail($renewedById);
    }

    private function slotRef(SignatureRequest $request, string $slot): string
    {
        return (string) $request->signers()->where('slot', $slot)->value('provider_signer_ref');
    }

    public function test_two_signers_completion_flow(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);

        $this->fake()->completeSigner($request->provider_ref, 'cliente');
        $request = $this->service()->reconcile($request);

        $this->assertSame(SignatureRequestStatus::Sent, $request->status);
        $this->assertFalse((bool) $document->fresh()->is_signed);
        $this->assertSame(SignerStatus::Signed, $request->signers->firstWhere('slot', 'cliente')->status);
        $this->assertNotSame(SignerStatus::Signed, $request->signers->firstWhere('slot', 'collaboratore')->status);
        $this->assertNotNull($request->last_synced_at);
        $this->assertCount(0, $document->fresh()->getMedia('signed'));

        $this->fake()->completeAll($request->provider_ref);
        $request = $this->service()->reconcile($request);

        $original = Document::withTrashed()->findOrFail($document->getKey());
        $document = $this->signedDocument($document);
        $this->assertSame(SignatureRequestStatus::Signed, $request->status);
        $this->assertNotNull($request->signed_at);
        $this->assertTrue((bool) $document->is_signed);
        $this->assertNotNull($document->signed_at);
        $this->assertSame($document->getKey(), $request->document_id);
        $this->assertCount(1, $document->getMedia('documents'));
        $this->assertSame('qav-rossi-firmato.pdf', $document->getFirstMedia('documents')->file_name);
        $this->assertTrue($original->trashed());
        $this->assertSame($document->getKey(), $original->replaced_by_id);
        $this->assertSame('qav.pdf', $original->getFirstMedia('documents')->file_name);
        $this->assertFalse((bool) $original->is_signed);

        $activity = Activity::query()->where('event', 'firma_completata')->sole();
        $this->assertSame($request->id, (int) $activity->subject_id);
        $this->assertEqualsCanonicalizing(['signature_request_id', 'provider', 'document_id', 'replaced_document_id'], array_keys($activity->properties->all()));
        $this->assertTrue($request->signers->every(fn ($s) => $s->status === SignerStatus::Signed));
    }

    public function test_deleted_documents_leave_the_expiry_schedule_and_the_signed_replacement_removes_the_original_row(): void
    {
        $document = $this->documentWithModule();
        $row = fn (Document $doc): array => [
            'document_id' => $doc->getKey(), 'documentable_group_key' => 'k', 'document_name' => 'QAV', 'document_type_name' => 'QAV',
            'entity_name' => 'ZZ', 'documentable_type' => 'client', 'documentable_id' => '1', 'status' => 'caricato', 'days_until_expiry' => 10,
        ];
        DocumentSchedule::create($row($document));
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);

        $this->service()->reconcile($request);

        $this->assertSame(0, DocumentSchedule::query()->where('document_id', $document->getKey())->count());

        $other = $this->documentWithModule();
        DocumentSchedule::create($row($other));
        $other->delete();
        $this->assertSame(0, DocumentSchedule::query()->where('document_id', $other->getKey())->count());
    }

    public function test_signed_document_replaces_the_original_for_the_kyc_questionnaire(): void
    {
        $document = $this->documentWithModule();
        $questionnaire = KycQuestionnaire::factory()->approved()->create(['client_id' => $document->documentable_id, 'document_id' => $document->getKey()]);
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);

        $this->service()->reconcile($request);

        $signed = $this->signedDocument($document);
        $this->assertSame($signed->getKey(), $questionnaire->fresh()->document_id);
        $this->assertNotNull($questionnaire->fresh()->document);
        $this->assertSame($document->name, $signed->name);
        $this->assertSame($document->document_type_id, $signed->document_type_id);
        $this->assertSame($document->getKey(), Document::withTrashed()->where('replaced_by_id', $signed->getKey())->value('id'));
    }

    public function test_reconcile_is_idempotent(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);

        $this->service()->reconcile($request);
        $this->service()->reconcile($request->fresh());
        $this->service()->reconcile(SignatureRequest::query()->find($request->id));

        $this->assertCount(1, $this->signedDocument($document)->getMedia('documents'));
        $this->assertSame(1, Activity::query()->where('event', 'firma_completata')->count());
    }

    public function test_stale_instance_cannot_complete_twice(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);

        $stale = SignatureRequest::query()->find($request->id);
        $this->service()->reconcile($request);
        $this->service()->reconcile($stale);

        $this->assertCount(1, $this->signedDocument($document)->getMedia('documents'));
        $this->assertSame(1, Activity::query()->where('event', 'firma_completata')->count());
    }

    public function test_download_failure_leaves_request_sent_and_document_unsigned(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('envelopeStatus')->andReturn(new EnvelopeStatus(SignatureRequestStatus::Signed, [
            new SignerState($this->slotRef($request, 'cliente'), SignerStatus::Signed, now()),
        ]));
        $provider->shouldReceive('downloadSigned')->andThrow(new ProviderUnavailableException('giu'));
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        try {
            $this->service()->reconcile($request);
            $this->fail('Attesa SignatureRequestException');
        } catch (SignatureRequestException) {
        }

        $this->assertSame(SignatureRequestStatus::Sent, $request->fresh()->status);
        $this->assertFalse((bool) $document->fresh()->is_signed);
        $this->assertCount(0, $document->fresh()->getMedia('signed'));
        $this->assertNotNull($request->fresh()->last_synced_at);
        $this->assertSame(0, Activity::query()->where('event', 'firma_completata')->count());
        $this->assertNotSame(SignerStatus::Signed, $request->signers()->where('slot', 'cliente')->first()->status);
    }

    public function test_status_failure_leaves_request_sent(): void
    {
        $request = $this->sentRequest();
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('envelopeStatus')->andThrow(new ProviderUnavailableException('giu'));
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        $this->expectException(SignatureRequestException::class);

        try {
            $this->service()->reconcile($request);
        } finally {
            $this->assertSame(SignatureRequestStatus::Sent, $request->fresh()->status);
        }
    }

    public function test_decline_and_provider_expiry_close_the_request_without_files(): void
    {
        foreach ([['decline', SignatureRequestStatus::Declined], ['expire', SignatureRequestStatus::Expired]] as [$method, $expected]) {
            $document = $this->documentWithModule();
            $request = $this->sentRequest($document);
            $this->fake()->{$method}($request->provider_ref);

            $request = $this->service()->reconcile($request);

            $this->assertSame($expected, $request->status);
            $this->assertNull($request->failure_reason);
            $this->assertFalse((bool) $document->fresh()->is_signed);
            $this->assertCount(0, $document->fresh()->getMedia('signed'));
        }
    }

    public function test_reconcile_ignores_closed_requests_and_unknown_signer_refs(): void
    {
        $request = $this->sentRequest();
        $request->update(['status' => SignatureRequestStatus::Cancelled]);
        $this->assertSame(SignatureRequestStatus::Cancelled, $this->service()->reconcile($request)->status);
        $this->assertNull($request->fresh()->last_synced_at);

        $open = $this->sentRequest();
        $open->signers()->update(['provider_signer_ref' => 'altro']);
        $this->assertSame(SignatureRequestStatus::Sent, $this->service()->reconcile($open)->status);
    }

    public function test_new_request_is_possible_after_decline(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);
        $this->fake()->decline($request->provider_ref);
        $this->service()->reconcile($request);

        $second = $this->service()->send($document, $this->signers(), User::factory()->create());

        $this->assertSame(SignatureRequestStatus::Sent, $second->fresh()->status);
    }

    public function test_cancel_open_request_cancels_the_envelope(): void
    {
        $request = $this->sentRequest();
        $user = User::factory()->create();

        $request = $this->service()->cancel($request, $user);

        $this->assertSame(SignatureRequestStatus::Cancelled, $request->status);
        $this->assertSame(SignatureRequestStatus::Cancelled, $this->fake()->envelopeStatus($request->provider_ref)->status);
        $activity = Activity::query()->where('event', 'firma_annullata')->sole();
        $this->assertSame($request->id, (int) $activity->subject_id);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertEqualsCanonicalizing(['signature_request_id', 'provider'], array_keys($activity->properties->all()));
    }

    public function test_cancel_closed_request_is_rejected(): void
    {
        $request = $this->sentRequest();
        $request->update(['status' => SignatureRequestStatus::Declined]);

        $this->expectException(SignatureRequestException::class);
        $this->service()->cancel($request, User::factory()->create());
    }

    public function test_cancel_pending_without_provider_ref_closes_locally(): void
    {
        $document = $this->documentWithModule();
        $request = SignatureRequest::create([
            'document_id' => $document->id, 'provider' => 'fake', 'status' => SignatureRequestStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldNotReceive('cancelEnvelope');
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        $request = $this->service()->cancel($request, User::factory()->create());

        $this->assertSame(SignatureRequestStatus::Cancelled, $request->status);
        $this->assertSame(1, Activity::query()->where('event', 'firma_annullata')->count());
    }

    public function test_expire_overdue(): void
    {
        $overdue = $this->sentRequest();
        $overdue->update(['expires_at' => now()->subHour()]);
        $fresh = $this->sentRequest();

        $this->assertSame(1, $this->service()->expireOverdue());

        $this->assertSame(SignatureRequestStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(SignatureRequestStatus::Cancelled, $this->fake()->envelopeStatus($overdue->provider_ref)->status);
        $this->assertSame(SignatureRequestStatus::Sent, $fresh->fresh()->status);
    }

    public function test_expire_overdue_swallows_provider_errors(): void
    {
        $overdue = $this->sentRequest();
        $overdue->update(['expires_at' => now()->subHour()]);
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('envelopeStatus')->andReturn(new EnvelopeStatus(SignatureRequestStatus::Sent, []));
        $provider->shouldReceive('cancelEnvelope')->andThrow(new ProviderUnavailableException('mario@example.test'));
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        $this->assertSame(1, $this->service()->expireOverdue());
        $this->assertSame(SignatureRequestStatus::Expired, $overdue->fresh()->status);
    }

    public function test_expire_overdue_fails_stale_pending_requests(): void
    {
        $document = $this->documentWithModule();
        $stale = SignatureRequest::create(['document_id' => $document->id, 'provider' => 'fake', 'status' => SignatureRequestStatus::Pending, 'expires_at' => now()->addDays(7)]);
        $stale->forceFill(['created_at' => now()->subMinutes(30)])->save();
        $recent = SignatureRequest::create(['document_id' => $document->id, 'provider' => 'fake', 'status' => SignatureRequestStatus::Pending, 'expires_at' => now()->addDays(7)]);

        $this->assertSame(1, $this->service()->expireOverdue());

        $this->assertSame(SignatureRequestStatus::Failed, $stale->fresh()->status);
        $this->assertSame('Richiesta non completata', $stale->fresh()->failure_reason);
        $this->assertSame(SignatureRequestStatus::Pending, $recent->fresh()->status);
    }

    public function test_apply_webhook(): void
    {
        $request = $this->sentRequest();

        $this->assertNull($this->service()->applyWebhook('fake', new SignatureEvent('sconosciuto', 'done', 'e1')));

        $found = $this->service()->applyWebhook('fake', new SignatureEvent($request->provider_ref, 'done', 'e2'));

        $this->assertSame($request->id, $found->id);
        $this->assertNotNull($request->fresh()->last_event_at);
        $this->assertSame(SignatureRequestStatus::Sent, $request->fresh()->status);
    }

    public function test_apply_webhook_is_scoped_by_provider(): void
    {
        $request = $this->sentRequest();

        $this->assertNull($this->service()->applyWebhook('yousign', new SignatureEvent($request->provider_ref, 'done', 'e3')));
        $this->assertNull($request->fresh()->last_event_at);
    }

    public function test_reconcile_uses_the_provider_stored_on_the_request(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);
        config(['signature.driver' => 'yousign']);

        $request = $this->service()->reconcile($request);

        $this->assertSame(SignatureRequestStatus::Signed, $request->status);
        $this->assertTrue((bool) $this->signedDocument($document)->is_signed);
    }

    public function test_expire_overdue_reconciles_a_completed_envelope_instead_of_expiring_it(): void
    {
        $document = $this->documentWithModule();
        $request = $this->sentRequest($document);
        $this->fake()->completeAll($request->provider_ref);
        $request->update(['expires_at' => now()->subMinute()]);

        $this->service()->expireOverdue();

        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
        $this->assertTrue((bool) $this->signedDocument($document)->is_signed);
        $this->assertCount(1, $this->signedDocument($document)->getMedia('documents'));
    }

    public function test_expire_overdue_skips_requests_whose_reconcile_fails(): void
    {
        $request = $this->sentRequest();
        $request->update(['expires_at' => now()->subMinute()]);
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('envelopeStatus')->andThrow(new ProviderUnavailableException('giu'));
        $provider->shouldNotReceive('cancelEnvelope');
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        $this->assertSame(0, $this->service()->expireOverdue());
        $this->assertSame(SignatureRequestStatus::Sent, $request->fresh()->status);
    }
}

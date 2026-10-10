<?php

namespace Tests\Feature;

use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Enums\SignerRole;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\Dto\EnvelopeStatus;
use App\Services\Signature\Exceptions\ProviderUnavailableException;
use App\Services\Signature\FakeSignatureProvider;
use App\Services\Signature\SignatureProvider;
use App\Services\Signature\SignatureProviderManager;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SignatureSyncCommandTest extends TestCase
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

    private function sentRequest(): SignatureRequest
    {
        $slots = [
            ['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 50.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
        ];
        $type = DocumentType::query()->create([
            'name' => 'QAV', 'slug' => 'qav-'.Str::random(6), 'nature' => 'template_fillable',
            'is_monitored' => true, 'duration' => 12, 'duration_unit' => 'months',
        ]);
        PdfModule::factory()->create(['name' => 'QAV', 'document_type_id' => $type->id, 'signature_slots' => $slots]);
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16))]);
        $document = $client->documents()->create([
            'name' => 'QAV Rossi', 'status' => 'caricato', 'spatie_collection' => 'documents', 'document_type_id' => $type->id,
        ]);
        $document->addMediaFromString(file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')))
            ->usingFileName('qav.pdf')->toMediaCollection('documents');

        $signers = [new SignerInput('cliente', SignerRole::Client, 'Mario', 'Rossi', 'mario@example.test', '338 1234567', 'RSSMRA80A01H501U', 'client', '1')];

        return app(SignatureRequestService::class)->send($document->fresh(), $signers, User::factory()->create())->fresh();
    }

    private function stale(SignatureRequest $request, ?int $syncedMinutesAgo = 120, ?int $eventMinutesAgo = 120): SignatureRequest
    {
        $request->forceFill([
            'last_synced_at' => $syncedMinutesAgo === null ? null : now()->subMinutes($syncedMinutesAgo),
            'last_event_at' => $eventMinutesAgo === null ? null : now()->subMinutes($eventMinutesAgo),
        ])->save();

        return $request->fresh();
    }

    private function fake(): FakeSignatureProvider
    {
        return new FakeSignatureProvider;
    }

    public function test_recent_requests_are_untouched(): void
    {
        $recentEvent = $this->stale($this->sentRequest(), 120, 5);
        $recentSync = $this->stale($this->sentRequest(), 5, 120);
        $this->fake()->completeAll($recentEvent->provider_ref);
        $this->fake()->completeAll($recentSync->provider_ref);

        $this->artisan('signature:sync')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Sent, $recentEvent->fresh()->status);
        $this->assertSame(SignatureRequestStatus::Sent, $recentSync->fresh()->status);
    }

    public function test_stale_requests_are_reconciled(): void
    {
        $request = $this->stale($this->sentRequest(), null, null);
        $this->fake()->completeAll($request->provider_ref);

        $this->artisan('signature:sync')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
        $this->assertTrue((bool) Document::query()->find($request->fresh()->document_id)->is_signed);
    }

    public function test_stale_minutes_option_overrides_config(): void
    {
        $request = $this->stale($this->sentRequest(), 10, 10);
        $this->fake()->completeAll($request->provider_ref);

        $this->artisan('signature:sync --stale-minutes=5')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
    }

    public function test_limit_picks_never_synced_first(): void
    {
        $synced = $this->stale($this->sentRequest(), 200, 200);
        $never = $this->stale($this->sentRequest(), null, 200);
        $this->fake()->completeAll($synced->provider_ref);
        $this->fake()->completeAll($never->provider_ref);

        $this->artisan('signature:sync --limit=1')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Signed, $never->fresh()->status);
        $this->assertSame(SignatureRequestStatus::Sent, $synced->fresh()->status);
    }

    public function test_overdue_requests_expire(): void
    {
        $request = $this->sentRequest();
        $request->update(['expires_at' => now()->subHour()]);

        $this->artisan('signature:sync')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Expired, $request->fresh()->status);
    }

    public function test_expire_overdue_orders_by_last_synced_nulls_first(): void
    {
        $synced = $this->sentRequest();
        $synced->update(['expires_at' => now()->subHour(), 'last_synced_at' => now()->subHour()]);
        $never = $this->sentRequest();
        $never->update(['expires_at' => now()->subHour(), 'last_synced_at' => null]);

        $this->assertSame(1, app(SignatureRequestService::class)->expireOverdue(1));

        $this->assertSame(SignatureRequestStatus::Expired, $never->fresh()->status);
        $this->assertSame(SignatureRequestStatus::Sent, $synced->fresh()->status);
    }

    public function test_failing_request_does_not_stop_others_and_log_has_only_id(): void
    {
        $bad = $this->stale($this->sentRequest(), null, null);
        $good = $this->stale($this->sentRequest(), 100, 100);
        $this->fake()->completeAll($good->provider_ref);

        $real = $this->fake();
        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('envelopeStatus')->andReturnUsing(function (string $ref) use ($bad, $real): EnvelopeStatus {
            if ($ref === $bad->provider_ref) {
                throw new ProviderUnavailableException('mario@example.test segreto');
            }

            return $real->envelopeStatus($ref);
        });
        $provider->shouldReceive('downloadSigned')->andReturnUsing(fn (string $ref): string => $real->downloadSigned($ref));
        $manager = Mockery::mock(SignatureProviderManager::class);
        $manager->shouldReceive('provider')->andReturn($provider);
        $this->app->instance(SignatureProviderManager::class, $manager);

        Log::spy();

        $this->artisan('signature:sync')->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Signed, $good->fresh()->status);
        $this->assertSame(SignatureRequestStatus::Sent, $bad->fresh()->status);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, (string) $bad->id) && ! str_contains($message, 'mario'))->atLeast()->once();
    }

    public function test_fake_complete_completes_and_reconciles(): void
    {
        $request = $this->sentRequest();

        $this->artisan('signature:fake-complete', ['ref' => $request->provider_ref])->assertSuccessful();

        $this->assertSame(SignatureRequestStatus::Signed, $request->fresh()->status);
    }

    public function test_fake_complete_decline_and_expire(): void
    {
        $declined = $this->sentRequest();
        $this->artisan('signature:fake-complete', ['ref' => $declined->provider_ref, '--decline' => true])->assertSuccessful();
        $this->assertSame(SignatureRequestStatus::Declined, $declined->fresh()->status);

        $expired = $this->sentRequest();
        $this->artisan('signature:fake-complete', ['ref' => $expired->provider_ref, '--expire' => true])->assertSuccessful();
        $this->assertSame(SignatureRequestStatus::Expired, $expired->fresh()->status);
    }

    public function test_fake_complete_refused_in_production(): void
    {
        $request = $this->sentRequest();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('signature:fake-complete', ['ref' => $request->provider_ref])->assertFailed();

        $this->assertSame(SignatureRequestStatus::Sent, $request->fresh()->status);
    }

    public function test_fake_complete_refused_with_other_driver(): void
    {
        $request = $this->sentRequest();
        config(['signature.driver' => 'yousign']);

        $this->artisan('signature:fake-complete', ['ref' => $request->provider_ref])->assertFailed();
    }
}

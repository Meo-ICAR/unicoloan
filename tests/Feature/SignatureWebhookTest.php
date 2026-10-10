<?php

namespace Tests\Feature;

use Unico\Core\Enums\SignatureRequestStatus;
use App\Jobs\ReconcileSignatureRequest;
use App\Models\SignatureRequest;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['signature.driver' => 'fake']);
        Queue::fake();
    }

    private function sentRequest(string $ref = 'fake-ref-1'): SignatureRequest
    {
        return SignatureRequest::create([
            'provider' => 'fake',
            'provider_ref' => $ref,
            'status' => SignatureRequestStatus::Sent,
            'document_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $ref, string $type = 'done', string $id = 'evt-1'): array
    {
        return ['ref' => $ref, 'type' => $type, 'id' => $id];
    }

    public function test_missing_or_wrong_signature_is_rejected_without_side_effects(): void
    {
        $request = $this->sentRequest();
        DB::connection('mysql')->enableQueryLog();

        $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'))->assertUnauthorized();
        $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'), ['X-Fake-Signature' => 'bad'])->assertUnauthorized();

        Queue::assertNothingPushed();
        $writes = collect(DB::connection('mysql')->getQueryLog())
            ->filter(fn (array $q): bool => preg_match('/^\s*(insert|update|delete)/i', $q['query']) === 1);
        $this->assertCount(0, $writes);
        $this->assertNull($request->fresh()->last_event_at);
    }

    public function test_valid_event_for_known_ref_queues_reconcile(): void
    {
        $request = $this->sentRequest();

        $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'), ['X-Fake-Signature' => 'ok'])
            ->assertStatus(202);

        Queue::assertPushed(ReconcileSignatureRequest::class, fn (ReconcileSignatureRequest $job): bool => $job->signatureRequestId === $request->id);
        $this->assertNotNull($request->fresh()->last_event_at);
    }

    public function test_same_event_twice_queues_a_single_unique_job(): void
    {
        $request = $this->sentRequest();

        foreach ([1, 2] as $_) {
            $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'), ['X-Fake-Signature' => 'ok'])->assertStatus(202);
        }

        Queue::assertPushed(ReconcileSignatureRequest::class, 1);
        $job = new ReconcileSignatureRequest($request->id);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame((string) $request->id, $job->uniqueId());
    }

    public function test_unknown_ref_returns_no_content(): void
    {
        $this->postJson('/api/signature/webhook/fake', $this->payload('nope'), ['X-Fake-Signature' => 'ok'])->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_ignored_event_returns_no_content(): void
    {
        $request = $this->sentRequest();

        $this->postJson('/api/signature/webhook/fake', ['foo' => 'bar'], ['X-Fake-Signature' => 'ok'])->assertNoContent();

        Queue::assertNothingPushed();
        $this->assertNull($request->fresh()->last_event_at);
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $this->postJson('/api/signature/webhook/sconosciuto', $this->payload('x'), ['X-Fake-Signature' => 'ok'])->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_job_ignores_unknown_request_id(): void
    {
        (new ReconcileSignatureRequest(999999))->handle(app(SignatureRequestService::class));

        $this->assertTrue(true);
    }

    public function test_job_retry_settings(): void
    {
        $job = new ReconcileSignatureRequest(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
        $this->assertGreaterThan(0, $job->uniqueFor);
    }

    public function test_non_active_provider_is_rejected_without_side_effects(): void
    {
        $request = $this->sentRequest();
        config(['signature.driver' => 'yousign']);
        DB::connection('mysql')->enableQueryLog();

        $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'), ['X-Fake-Signature' => 'ok'])->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertCount(0, DB::connection('mysql')->getQueryLog());
        $this->assertNull($request->fresh()->last_event_at);
    }

    public function test_provider_different_from_driver_is_not_found(): void
    {
        $this->postJson('/api/signature/webhook/yousign', $this->payload('x'))->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_fake_provider_is_not_found_in_production(): void
    {
        $request = $this->sentRequest();
        $this->app->detectEnvironment(fn () => 'production');

        $this->postJson('/api/signature/webhook/fake', $this->payload('fake-ref-1'), ['X-Fake-Signature' => 'ok'])->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertNull($request->fresh()->last_event_at);
    }
}

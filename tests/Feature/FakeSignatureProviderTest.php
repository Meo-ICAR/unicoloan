<?php

namespace Tests\Feature;

use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Enums\SignerStatus;
use App\Services\Signature\Exceptions\EnvelopeNotFoundException;
use App\Services\Signature\FakeSignatureProvider;
use App\Services\Signature\SignatureProvider;
use App\Services\Signature\SignatureProviderManager;
use Illuminate\Http\Request;
use Tests\Concerns\SignatureProviderContract;
use Tests\TestCase;

class FakeSignatureProviderTest extends TestCase
{
    use SignatureProviderContract;

    protected function provider(): SignatureProvider
    {
        return app(SignatureProviderManager::class)->provider('fake');
    }

    protected function completeAll(string $ref): void
    {
        $this->provider()->completeAll($ref);
    }

    public function test_status_advances_only_when_invoked(): void
    {
        $ref = $this->provider()->createEnvelope($this->makeEnvelope());

        $this->provider()->completeSigner($ref->providerRef, 'signer1');
        $status = $this->provider()->envelopeStatus($ref->providerRef);

        $this->assertSame(SignatureRequestStatus::Sent, $status->status);
        $byRef = collect($status->signers)->keyBy('providerSignerRef');
        $this->assertSame(SignerStatus::Signed, $byRef[$ref->signerRefs['signer1']]->status);
        $this->assertNotSame(SignerStatus::Signed, $byRef[$ref->signerRefs['signer2']]->status);
    }

    public function test_decline_and_expire(): void
    {
        $declined = $this->provider()->createEnvelope($this->makeEnvelope());
        $this->provider()->decline($declined->providerRef);
        $this->assertSame(SignatureRequestStatus::Declined, $this->provider()->envelopeStatus($declined->providerRef)->status);

        $expired = $this->provider()->createEnvelope($this->makeEnvelope());
        $this->provider()->expire($expired->providerRef);
        $this->assertSame(SignatureRequestStatus::Expired, $this->provider()->envelopeStatus($expired->providerRef)->status);
    }

    public function test_signed_download_is_original_pdf_plus_marker(): void
    {
        $ref = $this->provider()->createEnvelope($this->makeEnvelope());
        $this->provider()->completeAll($ref->providerRef);

        $pdf = $this->provider()->downloadSigned($ref->providerRef);

        $this->assertStringStartsWith($this->makeEnvelope()->pdf, $pdf);
        $this->assertStringEndsWith('%FAKE-SIGNED', rtrim($pdf));
    }

    public function test_unknown_envelope_throws(): void
    {
        $this->expectException(EnvelopeNotFoundException::class);

        $this->provider()->envelopeStatus('fake-inesistente');
    }

    public function test_parse_webhook_without_header_returns_null(): void
    {
        $request = Request::create('/x', 'POST', [], [], [], [], json_encode(['ref' => 'fake-1', 'type' => 'done', 'id' => 'e1']));

        $this->assertNull($this->provider()->parseWebhook($request));
    }

    public function test_parse_webhook_with_header_returns_event(): void
    {
        $request = Request::create('/x', 'POST', [], [], [], ['HTTP_X_FAKE_SIGNATURE' => 'ok'], json_encode(['ref' => 'fake-1', 'type' => 'done', 'id' => 'e1']));

        $event = $this->provider()->parseWebhook($request);

        $this->assertSame('fake-1', $event->providerRef);
        $this->assertSame('done', $event->type);
        $this->assertSame('e1', $event->eventId);
    }

    public function test_manager_resolves_default_driver_and_rejects_unknown(): void
    {
        config(['signature.driver' => 'fake']);

        $this->assertInstanceOf(FakeSignatureProvider::class, app(SignatureProviderManager::class)->provider());

        $this->expectException(\InvalidArgumentException::class);
        app(SignatureProviderManager::class)->provider('boh');
    }
}

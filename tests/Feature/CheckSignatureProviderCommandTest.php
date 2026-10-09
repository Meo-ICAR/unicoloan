<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckSignatureProviderCommandTest extends TestCase
{
    private const KEY = 'test-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['signature.yousign' => [
            'api_key' => self::KEY,
            'base_url' => 'https://yousign.test/v3',
            'webhook_secret' => 'test-webhook-secret',
            'signature_level' => 'electronic_signature',
            'timeout' => 5,
            'retry_delay_ms' => 0,
        ]]);
        Http::preventStrayRequests();
    }

    public function test_ok_prints_status_without_secrets(): void
    {
        config(['signature.driver' => 'yousign']);
        Http::fake(['*' => Http::response([], 200)]);

        $this->artisan('signature:check')
            ->expectsOutputToContain('Provider: yousign · URL: https://yousign.test/v3 · esito: OK (200)')
            ->doesntExpectOutputToContain(self::KEY)
            ->doesntExpectOutputToContain('test-webhook-secret')
            ->assertExitCode(0);

        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->url() === 'https://yousign.test/v3/signature_requests?limit=1');
    }

    public function test_401_prints_ko_and_fails(): void
    {
        config(['signature.driver' => 'yousign']);
        Http::fake(['*' => Http::response([], 401)]);

        $this->artisan('signature:check')
            ->expectsOutputToContain('esito: KO (401)')
            ->doesntExpectOutputToContain(self::KEY)
            ->assertExitCode(1);
    }

    public function test_connection_error_prints_ko_and_fails(): void
    {
        config(['signature.driver' => 'yousign']);
        Http::fake(['*' => fn () => throw new ConnectionException('boom '.self::KEY)]);

        $this->artisan('signature:check')
            ->expectsOutputToContain('esito: KO')
            ->doesntExpectOutputToContain(self::KEY)
            ->assertExitCode(1);
    }

    public function test_fake_driver_exits_zero_without_http(): void
    {
        config(['signature.driver' => 'fake']);

        $this->artisan('signature:check')
            ->expectsOutputToContain('fake')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }
}

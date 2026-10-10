<?php

namespace Tests\Feature;

use App\Services\CervedService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CervedServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cerved.api_key' => 'k', 'services.cerved.url' => 'https://cerved.test/score?codice_fiscale=']);
    }

    public function test_fetch_score_returns_first_score(): void
    {
        Http::fake(['cerved.test/*' => Http::response([
            'id_soggetto' => 1,
            'denominazione' => 'ACME SRL',
            'scores' => [['codice_score' => 'X', 'descrizione_score' => 'D', 'valore' => 5, 'categoria_codice' => 'C', 'categoria_descrizione' => 'CD']],
        ])]);

        $result = app(CervedService::class)->fetchScore('01234567890');

        $this->assertSame('ACME SRL', $result['denominazione']);
        $this->assertSame(5, $result['valore']);
        Http::assertSent(fn ($r) => $r->url() === 'https://cerved.test/score?codice_fiscale=01234567890' && $r->hasHeader('apikey', 'k'));
    }

    public function test_fetch_score_fails_without_scores(): void
    {
        Http::fake(['cerved.test/*' => Http::response(['scores' => []])]);

        $this->expectException(RuntimeException::class);
        app(CervedService::class)->fetchScore('01234567890');
    }

    public function test_fetch_score_fails_on_http_error(): void
    {
        Http::fake(['cerved.test/*' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);
        app(CervedService::class)->fetchScore('01234567890');
    }
}

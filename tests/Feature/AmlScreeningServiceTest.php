<?php

namespace Tests\Feature;

use App\Services\AmlScreeningService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AmlScreeningServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.aml.api_key' => 'k', 'services.aml.base_url' => 'https://aml.test', 'services.aml.min_score' => 0.88]);
    }

    public function test_search_pep_maps_matches_and_sends_expected_query(): void
    {
        Http::fake(['aml.test/*' => Http::response(['count' => 1, 'results' => [[
            'confidence_score' => 0.95, 'name' => 'Mario Rossi', 'title' => 'Sindaco', 'citizenship' => ['IT'],
            'data_source' => ['short_name' => 'PEP'],
        ]]])]);

        $matches = app(AmlScreeningService::class)->searchPep('Mario Rossi', true, '1980-01-01');

        $this->assertSame([['name' => 'Mario Rossi', 'score' => 0.95, 'title' => 'Sindaco', 'countries' => ['IT'], 'source' => 'PEP']], $matches);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer k')
            && $r['data_source'] === 'PEP' && $r['entity_type'] === 'Individual' && $r['date_of_birth'] === '1980-01-01' && $r['name'] === 'Mario Rossi');
    }

    public function test_search_pep_returns_empty_list_without_matches(): void
    {
        Http::fake(['aml.test/*' => Http::response(['count' => 0, 'results' => []])]);

        $this->assertSame([], app(AmlScreeningService::class)->searchPep('Nessuno', false));
        Http::assertSent(fn ($r) => $r['entity_type'] === 'Entity' && ! isset($r['date_of_birth']));
    }

    public function test_search_pep_fails_on_http_error_and_missing_key(): void
    {
        Http::fake(['aml.test/*' => Http::response('', 401)]);

        try {
            app(AmlScreeningService::class)->searchPep('X');
            $this->fail('Attesa RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
        }

        config(['services.aml.api_key' => null]);
        $this->expectException(RuntimeException::class);
        app(AmlScreeningService::class)->searchPep('X');
    }
}

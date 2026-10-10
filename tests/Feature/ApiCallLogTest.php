<?php

namespace Tests\Feature;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Filament\Resources\ApiCalls\Pages\ListApiCalls;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\User;
use App\Services\AmlScreeningService;
use App\Services\CervedService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ApiCallLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cerved.api_key' => 'secret-key', 'services.cerved.url' => 'https://cerved.test/score?cf=', 'services.cerved.cost' => 1.5,
            'services.aml.api_key' => 'secret-aml', 'services.aml.base_url' => 'https://aml.test', 'services.aml.cost' => 0.25,
        ]);
    }

    public function test_successful_cerved_call_is_logged_with_subject_and_cost(): void
    {
        Http::fake(['cerved.test/*' => Http::response(['id_soggetto' => 77, 'denominazione' => 'ACME', 'scores' => [['valore' => 5, 'categoria_descrizione' => 'Basso']]])]);
        $client = Client::create(['name' => 'Acme', 'is_person' => false]);

        app(CervedService::class)->fetchScore('01234567890', $client);

        $call = ApiCall::query()->sole();
        $this->assertSame(ApiProvider::Cerved, $call->provider);
        $this->assertSame(ApiCallStatus::Success, $call->status);
        $this->assertSame('1.5000', $call->cost);
        $this->assertTrue($call->subject->is($client));
        $this->assertSame('Score 5 - Basso', $call->summary);
        $this->assertSame('77', $call->reference);
        $this->assertSame(['codice_fiscale' => '01234567890'], $call->request);
        $this->assertStringNotContainsString('secret-key', json_encode($call->toArray()));
    }

    public function test_failed_call_is_logged_without_cost_and_rethrown(): void
    {
        Http::fake(['aml.test/*' => Http::response('boom', 500)]);

        try {
            app(AmlScreeningService::class)->searchPep('Mario Rossi');
            $this->fail('Attesa RuntimeException');
        } catch (RuntimeException) {
        }

        $call = ApiCall::query()->sole();
        $this->assertSame(ApiCallStatus::Failed, $call->status);
        $this->assertSame('0.0000', $call->cost);
        $this->assertSame(500, $call->http_status);
        $this->assertNull($call->subject_type);
    }

    public function test_pep_search_without_matches_is_billed_and_summarised(): void
    {
        Http::fake(['aml.test/*' => Http::response(['results' => [], 'search' => ['id' => 'abc-123']])]);

        app(AmlScreeningService::class)->searchPep('Mario Rossi', true, '1980-01-01');

        $call = ApiCall::query()->sole();
        $this->assertSame('0.2500', $call->cost);
        $this->assertSame('Nessuna corrispondenza', $call->summary);
        $this->assertSame('abc-123', $call->reference);
        $this->assertSame('pep_search', $call->operation);
    }

    public function test_admin_can_list_the_calls_with_their_total_cost(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $calls = ApiCall::factory()->count(2)->create();

        Livewire::test(ListApiCalls::class)
            ->assertCanSeeTableRecords($calls)
            ->filterTable('provider', ApiProvider::SanctionsIo->value)
            ->assertCanNotSeeTableRecords($calls);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\ApiCall;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPepActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pep_check_action_reports_matches(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        config(['services.aml.api_key' => 'k', 'services.aml.base_url' => 'https://aml.test']);
        Http::fake(['aml.test/*' => Http::response(['results' => [['confidence_score' => 0.97, 'name' => 'Mario Rossi', 'citizenship' => ['IT']]]])]);
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->callAction(TestAction::make('verificaPep')->schemaComponent('valutazioneAml', 'form'))
            ->assertNotified('Verifica PEP: 1 possibili corrispondenze');

        $this->assertTrue(ApiCall::query()->sole()->subject->is($client));
    }

    public function test_valid_tax_code_fills_birth_data_and_invalid_one_does_not(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $client = Client::create(['name' => 'Meo', 'first_name' => 'Piergiuseppe', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->set('data.tax_code', 'MEOPGS64D04F839X')
            ->assertNotified('Codice fiscale non valido')
            ->assertSet('data.birth_date', null)
            ->set('data.tax_code', 'MEOPGS64D04F839I')
            ->assertSet('data.birth_date', '1964-04-04')
            ->assertSet('data.sex', 'M')
            ->assertSet('data.birth_place', 'Napoli (NA)');
    }

    public function test_api_actions_do_not_run_on_an_unsaved_client(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        config(['services.aml.api_key' => 'k', 'services.cerved.api_key' => 'k', 'services.cerved.url' => 'https://cerved.test/?cf=']);
        Http::fake();

        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'Rossi', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U'])
            ->mountAction(TestAction::make('cerved')->schemaComponent('tax_code', 'form'))
            ->assertNotified('Salva prima il cliente')
            ->assertActionNotMounted()
            ->mountAction(TestAction::make('verificaPep')->schemaComponent('valutazioneAml', 'form'))
            ->assertNotified('Salva prima il cliente');

        Http::assertNothingSent();
        $this->assertSame(0, ApiCall::query()->count());
    }

    public function test_api_actions_do_not_run_with_unsaved_changes_to_the_sent_data(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        config(['services.aml.api_key' => 'k']);
        Http::fake();
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->set('data.first_name', 'Luigi')
            ->mountAction(TestAction::make('verificaPep')->schemaComponent('valutazioneAml', 'form'))
            ->assertNotified('Salva le modifiche prima di interrogare')
            ->assertActionNotMounted();

        Http::assertNothingSent();
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ClientMandatesRelationManager;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\LeadSource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ClientEditLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    private function makeClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ], $attributes));
    }

    public function test_edit_form_exposes_kyc_aml_and_altro_tabs_with_collapsed_mandates(): void
    {
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertSee('Anagrafica')
            ->assertSee('KYC / AML')
            ->assertSee('Altro')
            ->assertSee('Mandati Cliente')
            ->assertSee('Tipologia e Lead')
            ->assertDontSee('Compliance AML');
    }

    public function test_tipologia_and_lead_source_options_are_preloaded_on_altro_tab(): void
    {
        $type = ClientType::query()->create([
            'name' => 'Cliente Persona Fisica',
            'is_person' => true,
            'is_company' => false,
        ]);
        $source = LeadSource::query()->create([
            'name' => 'Web',
            'type' => 'website',
            'is_active' => true,
        ]);
        $client = $this->makeClient([
            'client_type_id' => $type->id,
            'is_lead' => true,
            'leadsource_id' => $source->id,
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'client_type_id' => $type->id,
                'is_lead' => true,
                'leadsource_id' => $source->id,
            ])
            ->assertSee('Cliente Persona Fisica')
            ->assertSee('Web');
    }

    public function test_mandates_are_embedded_in_a_collapsed_anagrafica_section(): void
    {
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertSee('Mandati Cliente')
            ->assertSee('Massimo 3 mandati per cliente');

        Livewire::test(ClientMandatesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => EditClient::class,
        ])->assertSuccessful();
    }

    public function test_openapi_action_is_available_on_iban_for_companies(): void
    {
        $company = $this->makeClient([
            'name' => 'Acme Spa',
            'first_name' => null,
            'is_person' => false,
            'tax_code' => '12485671007',
        ]);

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('iban')
            ->assertSee('Openapi');
    }

    public function test_kyc_action_is_listed_before_pep_on_the_compliance_section(): void
    {
        $client = $this->makeClient();

        $html = Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertSee('KYC (adeguata verifica)')
            ->assertSee('Verifica PEP')
            ->html();

        $this->assertLessThan(
            strpos($html, 'Verifica PEP'),
            strpos($html, 'KYC (adeguata verifica)'),
            'Il tasto KYC deve comparire prima di Verifica PEP',
        );
    }

    public function test_subfornitori_and_privacy_supplier_fields_are_visible_only_when_is_company(): void
    {
        $client = $this->makeClient(['is_company' => false]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertFormFieldIsHidden('subfornitori')
            ->assertFormFieldIsHidden('dpo_email')
            ->fillForm(['is_company' => true])
            ->assertFormFieldIsVisible('subfornitori')
            ->assertFormFieldIsVisible('dpo_email')
            ->assertFormFieldIsVisible('privacy_contact_email')
            ->assertSee('Fornitore / soggetto privacy');
    }

    public function test_missing_privacy_and_admin_fields_are_present_on_the_form(): void
    {
        $client = $this->makeClient(['is_company' => true, 'is_lead' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('privacy_policy_read_at')
            ->assertFormFieldExists('consent_special_categories_at')
            ->assertFormFieldExists('privacy_consent')
            ->assertFormFieldExists('is_company')
            ->assertFormFieldExists('acquired_at')
            ->assertFormFieldExists('salary')
            ->assertFormFieldExists('vat_number')
            ->assertFormFieldExists('contoCOGE')
            ->assertFormFieldExists('is_client');
    }
}

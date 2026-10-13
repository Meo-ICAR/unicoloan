<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\Resources\Praticas\Pages\CreatePratica;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinancingTypeVisibilityTest extends TestCase
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
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function person(): Client
    {
        return Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16))]);
    }

    private function company(): Client
    {
        return Client::create(['name' => 'Acme Srl', 'is_person' => false, 'tax_code' => '12485671007']);
    }

    private function pratica(string $product, ?Client $client = null): Pratica
    {
        return Pratica::create([
            'codice_pratica' => 'P-'.Str::random(5),
            'nome_cliente' => 'Mario',
            'cognome_cliente' => 'Rossi',
            'tipo_prodotto' => $product,
            'stato_pratica' => 'INSERITA',
            'codice_fiscale' => $client?->tax_code,
            'rata' => 250,
            'nrate' => 120,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function relationManagers(Client $client, ?Pratica $pratica): array
    {
        return array_values(Livewire::withQueryParams(['pratica' => $pratica?->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->instance()
            ->getRelationManagers());
    }

    public function test_pratica_form_shows_installments_for_cessione_but_not_for_tfs(): void
    {
        Livewire::test(EditPratica::class, ['record' => $this->pratica('Cessione')->getKey()])
            ->assertSee('Tipo finanziamento: Cessione del quinto')
            ->assertFormFieldVisible('rata')
            ->assertFormFieldVisible('nrate')
            ->assertFormFieldVisible('nome_cliente');

        Livewire::test(EditPratica::class, ['record' => $this->pratica('TFS')->getKey()])
            ->assertSee('Tipo finanziamento: Anticipo TFS')
            ->assertFormFieldHidden('rata')
            ->assertFormFieldHidden('nrate')
            ->assertFormFieldVisible('nome_cliente');
    }

    public function test_business_pratica_hides_the_individual_first_name(): void
    {
        Livewire::test(EditPratica::class, ['record' => $this->pratica('Aziendale')->getKey()])
            ->assertFormFieldHidden('nome_cliente')
            ->assertSee('Ragione Sociale')
            ->assertFormFieldVisible('rata');
    }

    public function test_property_description_is_shown_only_for_mortgages(): void
    {
        Livewire::test(EditPratica::class, ['record' => $this->pratica('Mutuo')->getKey()])
            ->assertFormFieldVisible('descrizione_immobile');

        Livewire::test(EditPratica::class, ['record' => $this->pratica('Prestito')->getKey()])
            ->assertFormFieldHidden('descrizione_immobile');

        Livewire::test(EditPratica::class, ['record' => $this->pratica('Polizza')->getKey()])
            ->assertFormFieldVisible('rata')
            ->assertFormFieldHidden('descrizione_immobile');
    }

    public function test_property_description_is_saved_on_a_mortgage(): void
    {
        $pratica = $this->pratica('Mutuo');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['descrizione_immobile' => 'Appartamento in Via Roma 1, Milano - Fg. 10 Part. 20 Sub. 3'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Appartamento in Via Roma 1, Milano - Fg. 10 Part. 20 Sub. 3', $pratica->refresh()->descrizione_immobile);
    }

    public function test_pratica_form_follows_the_product_while_editing(): void
    {
        Livewire::test(CreatePratica::class)
            ->assertFormFieldVisible('rata')
            ->assertFormFieldHidden('descrizione_immobile')
            ->fillForm(['tipo_prodotto' => 'TFS'])
            ->assertFormFieldHidden('rata')
            ->fillForm(['tipo_prodotto' => 'Mutuo'])
            ->assertFormFieldVisible('rata')
            ->assertFormFieldVisible('descrizione_immobile');
    }

    public function test_saving_keeps_the_values_of_hidden_fields(): void
    {
        $pratica = $this->pratica('TFS');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormSet(['tipo_prodotto' => 'TFS', 'stato_pratica' => 'INSERITA'])
            ->fillForm(['erogato' => 5000])
            ->call('save')
            ->assertHasNoFormErrors();

        $pratica->refresh();
        $this->assertSame('250.00', $pratica->rata);
        $this->assertSame(120, $pratica->nrate);
        $this->assertSame('Mario', $pratica->nome_cliente);
    }

    public function test_client_action_opens_the_client_with_the_pratica_context(): void
    {
        $client = $this->person();
        $pratica = $this->pratica('Cessione', $client);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionVisible('scheda_cliente')
            ->assertActionHasUrl('scheda_cliente', ClientResource::getUrl('edit', ['record' => $client, 'pratica' => $pratica->getKey()]));

        Livewire::test(EditPratica::class, ['record' => $this->pratica('Cessione')->getKey()])
            ->assertActionHidden('scheda_cliente');
    }

    public function test_client_opened_from_a_cessione_shows_salary_data_only(): void
    {
        $client = $this->person();
        $pratica = $this->pratica('Cessione', $client);

        Livewire::withQueryParams(['pratica' => $pratica->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->assertSee('Vista per la pratica '.$pratica->codice_pratica.' · Cessione del quinto')
            ->assertFormFieldVisible('employer_id')
            ->assertFormFieldVisible('salary')
            ->assertFormFieldVisible('salary_quote')
            ->assertFormFieldVisible('birth_date')
            ->assertActionVisible('torna_pratica')
            ->assertActionVisible('mostra_tutto');

        $this->assertContains(ImpegniTerziRelationManager::class, $this->relationManagers($client, $pratica));
    }

    public function test_client_opened_from_a_mortgage_or_tfs_hides_what_does_not_apply(): void
    {
        $client = $this->person();
        $mutuo = $this->pratica('Mutuo', $client);
        $tfs = $this->pratica('TFS', $client);

        Livewire::withQueryParams(['pratica' => $mutuo->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldVisible('salary')
            ->assertFormFieldHidden('salary_quote');

        $this->assertNotContains(ImpegniTerziRelationManager::class, $this->relationManagers($client, $tfs));
    }

    public function test_client_opened_from_a_business_pratica_shows_company_data_and_hides_personal_data(): void
    {
        $company = $this->company();
        $pratica = $this->pratica('Aziendale', $company);

        Livewire::withQueryParams(['pratica' => $pratica->getKey()])
            ->test(EditClient::class, ['record' => $company->getKey()])
            ->assertFormFieldVisible('ateco_code')
            ->assertFormFieldVisible('turnover')
            ->assertFormFieldVisible('legal_representative_id')
            ->assertFormFieldHidden('birth_date')
            ->assertFormFieldHidden('citizenship');

        $this->assertContains(ClientRelationsRelationManager::class, $this->relationManagers($company, $pratica));
    }

    public function test_company_data_is_hidden_when_the_company_is_opened_from_a_personal_loan(): void
    {
        $company = $this->company();
        $pratica = $this->pratica('Prestito', $company);

        Livewire::withQueryParams(['pratica' => $pratica->getKey()])
            ->test(EditClient::class, ['record' => $company->getKey()])
            ->assertFormFieldHidden('ateco_code')
            ->assertFormFieldHidden('legal_representative_id');

        $this->assertNotContains(ClientRelationsRelationManager::class, $this->relationManagers($company, $pratica));
    }

    public function test_client_without_context_shows_everything(): void
    {
        $client = $this->person();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertDontSee('Vista per la pratica')
            ->assertFormFieldVisible('employer_id')
            ->assertFormFieldVisible('salary')
            ->assertFormFieldVisible('salary_quote')
            ->assertFormFieldVisible('birth_date')
            ->assertActionHidden('torna_pratica')
            ->assertActionHidden('mostra_tutto');

        $this->assertContains(ImpegniTerziRelationManager::class, $this->relationManagers($client, null));
    }

    public function test_a_pratica_of_another_client_does_not_filter_the_client(): void
    {
        $client = $this->person();
        $other = $this->pratica('Mutuo', $this->person());

        Livewire::withQueryParams(['pratica' => $other->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->assertDontSee('Vista per la pratica')
            ->assertFormFieldVisible('salary_quote')
            ->assertActionHidden('torna_pratica');
    }

    public function test_salary_data_entered_from_the_client_form_is_saved(): void
    {
        $client = $this->person();
        $pratica = $this->pratica('Cessione', $client);

        Livewire::withQueryParams(['pratica' => $pratica->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm(['salary' => 1800, 'salary_quote' => 360])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertSame('1800.00', $client->salary);
        $this->assertSame('360.00', $client->salary_quote);
    }
}

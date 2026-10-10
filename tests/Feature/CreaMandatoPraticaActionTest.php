<?php

namespace Tests\Feature;

use App\Enums\PraticaClientRole;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\ClientMandate;
use App\Models\PraticaClient;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreaMandatoPraticaActionTest extends TestCase
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

        foreach (['Mutuo', 'Prestito', 'Cessione'] as $product) {
            Tipoprodotto::query()->updateOrCreate(['name' => $product], ['for_person' => true, 'for_company' => true, 'requires_mandate' => true]);
        }
    }

    public function test_action_creates_mandate_and_first_pratica_and_registers_the_applicant(): void
    {
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->callAction('creaMandatoPratica', ['tipo_prodotto' => 'Mutuo', 'amount' => 150000, 'scopo_finanziamento' => 'Acquisto casa'])
            ->assertNotified();

        $mandate = ClientMandate::query()->where('client_id', $client->getKey())->sole();
        $this->assertMatchesRegularExpression('/^MAND-\d{6}-\d{4}$/', $mandate->numero_mandato);
        $this->assertSame('attivo', $mandate->stato);
        $this->assertEquals(150000, $mandate->importo_richiesto_mandato);
        $this->assertSame('Acquisto casa', $mandate->scopo_finanziamento);

        $pratica = Pratica::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->sole();
        $this->assertSame(['Mario', 'Rossi', 'Mutuo', 'INSERITA'], [$pratica->nome_cliente, $pratica->cognome_cliente, $pratica->tipo_prodotto, $pratica->stato_pratica]);
        $this->assertEquals(150000, $pratica->amount);
        $this->assertFalse((bool) $pratica->is_notowned);

        $this->assertSame(PraticaClientRole::Applicant, PraticaClient::query()->where('pratica_id', $pratica->getKey())->sole()->role);
    }

    public function test_second_mandate_gets_the_next_progressive_number(): void
    {
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        foreach ([1, 2] as $ignored) {
            Livewire::test(EditClient::class, ['record' => $client->getKey()])
                ->callAction('creaMandatoPratica', ['tipo_prodotto' => 'Prestito', 'amount' => 5000]);
        }

        $numbers = ClientMandate::query()->orderBy('numero_mandato')->pluck('numero_mandato')->all();
        $this->assertSame(['MAND-000001-'.date('Y'), 'MAND-000002-'.date('Y')], $numbers);
    }

    public function test_cessione_has_no_applicant_row_and_a_company_uses_its_name_as_surname(): void
    {
        $company = Client::create(['name' => 'Acme Srl', 'is_person' => false, 'tax_code' => '12485671007']);

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->callAction('creaMandatoPratica', ['tipo_prodotto' => 'Cessione', 'amount' => 20000]);

        $pratica = Pratica::query()->where('codice_fiscale', '12485671007')->sole();
        $this->assertNull($pratica->nome_cliente);
        $this->assertSame('Acme Srl', $pratica->cognome_cliente);
        $this->assertSame(0, PraticaClient::query()->count());
    }

    public function test_action_is_disabled_without_tax_code(): void
    {
        $client = Client::create(['name' => 'Senza CF', 'is_person' => true]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])->assertActionDisabled('creaMandatoPratica');
    }

    public function test_product_without_mandate_creates_only_the_pratica(): void
    {
        Tipoprodotto::query()->updateOrCreate(['name' => 'Utenza'], ['requires_mandate' => false]);
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->callAction('creaMandatoPratica', ['tipo_prodotto' => 'Utenza', 'amount' => 300])
            ->assertNotified('Pratica '.Pratica::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->value('codice_pratica').' creata (prodotto senza mandato)');

        $this->assertSame(0, ClientMandate::query()->count());
        $this->assertSame(1, Pratica::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->count());
    }

    public function test_products_are_filtered_by_client_type(): void
    {
        Tipoprodotto::query()->updateOrCreate(['name' => 'SoloFisica'], ['for_person' => true, 'for_company' => false]);
        Tipoprodotto::query()->updateOrCreate(['name' => 'SoloGiuridica'], ['for_person' => false, 'for_company' => true]);
        $person = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);
        $company = Client::create(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007']);

        Livewire::test(EditClient::class, ['record' => $person->getKey()])
            ->mountAction('creaMandatoPratica')
            ->assertSchemaComponentExists('tipo_prodotto', checkComponentUsing: fn ($c) => array_key_exists('SoloFisica', $c->getOptions()) && ! array_key_exists('SoloGiuridica', $c->getOptions()));

        Livewire::test(EditClient::class, ['record' => $company->getKey()])
            ->mountAction('creaMandatoPratica')
            ->assertSchemaComponentExists('tipo_prodotto', checkComponentUsing: fn ($c) => ! array_key_exists('SoloFisica', $c->getOptions()) && array_key_exists('SoloGiuridica', $c->getOptions()));
    }

    public function test_third_party_products_are_not_offered_for_a_new_pratica(): void
    {
        Tipoprodotto::query()->updateOrCreate(['name' => 'ImpegnoTerzi'], ['is_third_party' => true, 'for_person' => true, 'for_company' => true]);
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->mountAction('creaMandatoPratica')
            ->assertSchemaComponentExists('tipo_prodotto', checkComponentUsing: fn ($c) => array_key_exists('Mutuo', $c->getOptions()) && ! array_key_exists('ImpegnoTerzi', $c->getOptions()));
    }
}

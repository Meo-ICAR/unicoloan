<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ImpegniTerziRelationManager;
use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImpegniTerziActionTest extends TestCase
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
        Tipoprodotto::query()->updateOrCreate(['name' => 'Mutuo'], ['for_person' => true, 'for_company' => true]);
        Tipoprodotto::query()->updateOrCreate(['name' => 'Sindacato'], ['for_person' => true, 'for_company' => false]);
        Tipoprodotto::query()->updateOrCreate(['name' => 'Leasing strumentale'], ['for_person' => false, 'for_company' => true]);
    }

    private function manager(Client $client)
    {
        return Livewire::test(ImpegniTerziRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class]);
    }

    public function test_commitment_is_added_as_a_not_owned_pratica_of_the_client(): void
    {
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        $this->manager($client)
            ->callAction(TestAction::make('aggiungiImpegno')->table(), ['tipo_prodotto' => 'Mutuo', 'denominazione_banca' => 'Altra Banca', 'rata' => 450.5, 'nrate' => 120, 'amount' => 60000])
            ->assertNotified('Impegno aggiunto');

        $pratica = Pratica::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->sole();
        $this->assertTrue((bool) $pratica->is_notowned);
        $this->assertSame(['Mutuo', 'Altra Banca', 'PERFEZIONATA'], [$pratica->tipo_prodotto, $pratica->denominazione_banca, $pratica->stato_pratica]);
        $this->assertEquals(450.5, $pratica->rata);
        $this->assertStringStartsWith('TERZI-', $pratica->codice_pratica);
        $this->manager($client)->assertCanSeeTableRecords([$pratica]);
    }

    public function test_commitment_can_be_added_with_every_field_empty(): void
    {
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);

        $this->manager($client)
            ->callAction(TestAction::make('aggiungiImpegno')->table(), [])
            ->assertHasNoFormErrors()
            ->assertNotified('Impegno aggiunto');

        $pratica = Pratica::query()->where('codice_fiscale', 'RSSMRA80A01H501U')->sole();
        $this->assertNull($pratica->tipo_prodotto);
        $this->assertNull($pratica->rata);
        $this->assertTrue((bool) $pratica->is_notowned);
    }

    public function test_products_follow_the_client_type(): void
    {
        $person = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);
        $company = Client::create(['name' => 'Acme', 'is_person' => false, 'tax_code' => '12485671007']);

        $this->manager($person)->mountAction(TestAction::make('aggiungiImpegno')->table())
            ->assertSchemaComponentExists('tipo_prodotto', checkComponentUsing: fn ($c) => array_key_exists('Sindacato', $c->getOptions()) && ! array_key_exists('Leasing strumentale', $c->getOptions()));

        $this->manager($company)->mountAction(TestAction::make('aggiungiImpegno')->table())
            ->assertSchemaComponentExists('tipo_prodotto', checkComponentUsing: fn ($c) => array_key_exists('Leasing strumentale', $c->getOptions()) && ! array_key_exists('Sindacato', $c->getOptions()));
    }

    public function test_action_is_disabled_without_tax_code(): void
    {
        $client = Client::create(['name' => 'Senza CF', 'is_person' => true]);

        $this->manager($client)->assertActionDisabled(TestAction::make('aggiungiImpegno')->table());
    }
}

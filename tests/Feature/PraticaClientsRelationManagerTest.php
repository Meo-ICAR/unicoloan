<?php

namespace Tests\Feature;

use App\Enums\PraticaClientRole;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\RelationManagers\PraticaClientsRelationManager;
use App\Models\Client;
use App\Models\PraticaClient;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PraticaClientsRelationManagerTest extends TestCase
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

    private function pratica(string $product): Pratica
    {
        return Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'P-'.Str::random(5), 'tipo_prodotto' => $product]);
    }

    private function manager(Pratica $pratica)
    {
        return Livewire::test(PraticaClientsRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => EditPratica::class]);
    }

    public function test_tab_is_hidden_for_cessione_and_delega_and_shown_for_other_products(): void
    {
        foreach (['Cessione', 'Delega', 'ALTRA DELEGAZIONE IMPORTO CONTENUTO'] as $product) {
            $this->assertFalse(PraticaClientsRelationManager::canViewForRecord($this->pratica($product), EditPratica::class), $product);
        }

        foreach (['Mutuo', 'Prestito', 'TFS', 'CHIROGRAFARIO'] as $product) {
            $this->assertTrue(PraticaClientsRelationManager::canViewForRecord($this->pratica($product), EditPratica::class), $product);
        }
    }

    public function test_applicant_co_obligor_and_guarantor_can_be_added_and_listed(): void
    {
        $pratica = $this->pratica('Mutuo');
        $rossi = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => 'RSSMRA80A01H501U']);
        $bianchi = Client::create(['name' => 'Bianchi', 'first_name' => 'Luca', 'is_person' => true, 'tax_code' => 'BNCLCU85M01H501Z']);

        $manager = $this->manager($pratica);

        foreach ([[$rossi, PraticaClientRole::Applicant], [$bianchi, PraticaClientRole::CoObligor], [$bianchi, PraticaClientRole::Guarantor]] as [$client, $role]) {
            $manager->callAction(TestAction::make('create')->table(), ['client_id' => $client->getKey(), 'role' => $role->value])->assertHasNoFormErrors();
        }

        $this->assertSame(3, PraticaClient::query()->where('pratica_id', $pratica->getKey())->count());
        $manager->assertCanSeeTableRecords(PraticaClient::query()->where('pratica_id', $pratica->getKey())->get())
            ->assertSee('Coobbligato')->assertSee('Garante')->assertSee('BNCLCU85M01H501Z');
    }

    public function test_same_client_cannot_have_the_same_role_twice(): void
    {
        $pratica = $this->pratica('Mutuo');
        $rossi = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true]);
        PraticaClient::create(['pratica_id' => $pratica->getKey(), 'client_id' => $rossi->getKey(), 'role' => PraticaClientRole::Guarantor]);

        $this->manager($pratica)
            ->callAction(TestAction::make('create')->table(), ['client_id' => $rossi->getKey(), 'role' => PraticaClientRole::Guarantor->value])
            ->assertHasFormErrors(['role' => 'unique']);

        $this->assertSame(1, PraticaClient::query()->count());
    }
}

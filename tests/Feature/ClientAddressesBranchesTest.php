<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\RelationManagers\BranchesRelationManager;
use App\Models\Client;
use App\Models\Pratica;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\Address;

class ClientAddressesBranchesTest extends TestCase
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

    /**
     * @return array<int, string>
     */
    private function relationManagers(Client $client, ?string $product = null): array
    {
        $pratica = $product === null ? null : Pratica::create([
            'codice_pratica' => 'P-'.Str::random(5),
            'tipo_prodotto' => $product,
            'codice_fiscale' => $client->tax_code,
        ]);

        return array_values(Livewire::withQueryParams(['pratica' => $pratica?->getKey()])
            ->test(EditClient::class, ['record' => $client->getKey()])
            ->instance()
            ->getRelationManagers());
    }

    public function test_natural_person_shows_addresses_and_not_branches(): void
    {
        $managers = $this->relationManagers($this->person());

        $this->assertContains(AddressesRelationManager::class, $managers);
        $this->assertNotContains(BranchesRelationManager::class, $managers);
    }

    public function test_legal_entity_shows_branches_and_not_addresses(): void
    {
        $managers = $this->relationManagers($this->company());

        $this->assertContains(BranchesRelationManager::class, $managers);
        $this->assertNotContains(AddressesRelationManager::class, $managers);
    }

    public function test_the_choice_ignores_the_financing_type_context(): void
    {
        $this->assertContains(BranchesRelationManager::class, $this->relationManagers($this->company(), 'Prestito'));
        $this->assertContains(AddressesRelationManager::class, $this->relationManagers($this->person(), 'Aziendale'));
    }

    public function test_address_is_added_to_the_natural_person(): void
    {
        $client = $this->person();

        Livewire::test(AddressesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->callAction(TestAction::make('create')->table(), ['street' => 'Via Roma', 'numero' => '1', 'zip_code' => '20121', 'city' => 'Milano'])
            ->assertHasNoFormErrors()
            ->assertSee('Via Roma 1');

        $address = Address::query()->sole();
        $this->assertSame('client', $address->addressable_type);
        $this->assertSame($client->getKey(), (int) $address->addressable_id);
        $this->assertTrue($client->addresses()->whereKey($address->getKey())->exists());
    }
}

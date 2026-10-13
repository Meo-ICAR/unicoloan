<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientCompanyPrivacyFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    public function test_client_resource_excludes_is_company_records(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $client = Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'is_company' => false,
        ]);
        $supplier = Client::create([
            'name' => 'Fornitore Spa',
            'is_person' => false,
            'is_company' => true,
        ]);

        $this->assertTrue(
            ClientResource::getEloquentQuery()->whereKey($client->getKey())->exists()
        );
        $this->assertFalse(
            ClientResource::getEloquentQuery()->whereKey($supplier->getKey())->exists()
        );

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$client])
            ->assertCanNotSeeTableRecords([$supplier]);
    }

    public function test_create_client_forces_is_company_false(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Verdi',
                'first_name' => 'Luigi',
                'is_person' => true,
                'status' => 'raccolta_dati',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(
            (bool) Client::query()->where('name', 'Verdi')->sole()->is_company
        );
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ThirdPartyCommitmentsTest extends TestCase
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

    public function test_third_party_commitments_are_not_listed_among_the_pratiche_but_shown_on_the_client(): void
    {
        $client = Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTEST80A01H501A']);
        $own = Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'ZZ-OWN', 'codice_fiscale' => $client->tax_code, 'cognome_cliente' => 'ZZ ROSSI']);
        $third = Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'ZZ-TERZI', 'codice_fiscale' => $client->tax_code, 'cognome_cliente' => 'ZZ ROSSI', 'is_notowned' => true]);

        $listed = Livewire::test(ListPraticas::class)->instance()->getTable()->getQuery()
            ->whereKey([$own->getKey(), $third->getKey()])
            ->pluck('id')
            ->all();

        $this->assertSame([$own->getKey()], $listed);

        Livewire::test(ImpegniTerziRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
            ->assertCanSeeTableRecords([$third])
            ->assertCanNotSeeTableRecords([$own]);
    }
}

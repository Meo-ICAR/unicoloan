<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\ListPraticas;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PraticasListFiltersTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pratica(array $attributes = []): Pratica
    {
        return Pratica::create($attributes + [
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'ZZ-'.Str::random(6),
            'stato_pratica' => 'INSERITA',
            'data_inserimento_pratica' => today(),
        ]);
    }

    public function test_default_list_hides_rejected_and_erogated_pratiche(): void
    {
        $open = $this->pratica();
        $rejected = $this->pratica(['rejected_at' => today()]);
        $erogated = $this->pratica(['erogated_at' => today()]);

        Livewire::test(ListPraticas::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$rejected, $erogated]);
    }

    public function test_removing_the_default_filters_shows_everything(): void
    {
        $open = $this->pratica();
        $rejected = $this->pratica(['rejected_at' => today()]);
        $erogated = $this->pratica(['erogated_at' => today()]);

        Livewire::test(ListPraticas::class)
            ->removeTableFilter('not_rejected')
            ->removeTableFilter('not_erogated')
            ->assertCanSeeTableRecords([$open, $rejected, $erogated]);
    }

    public function test_suspended_pratiche_are_no_longer_hidden_by_default(): void
    {
        $suspended = $this->pratica(['stato_pratica' => 'SOSPESA']);

        Livewire::test(ListPraticas::class)->assertCanSeeTableRecords([$suspended]);
    }

    public function test_istituto_select_lists_banks_from_clienti_and_filters_by_denominazione_banca(): void
    {
        Clienti::create(['name' => 'ZZ BANCA UNO', 'principal_type' => 'banca']);
        Clienti::create(['name' => 'ZZ BROKER', 'principal_type' => 'broker']);
        $one = $this->pratica(['denominazione_banca' => 'ZZ BANCA UNO']);
        $two = $this->pratica(['denominazione_banca' => 'ZZ BANCA DUE']);

        $component = Livewire::test(ListPraticas::class);
        $options = $component->instance()->getTable()->getFilter('istituto_erogazione')->getOptions();

        $this->assertArrayHasKey('ZZ BANCA UNO', $options);
        $this->assertArrayNotHasKey('ZZ BROKER', $options);

        $component
            ->filterTable('istituto_erogazione', ['ZZ BANCA UNO'])
            ->assertCanSeeTableRecords([$one])
            ->assertCanNotSeeTableRecords([$two]);
    }
}

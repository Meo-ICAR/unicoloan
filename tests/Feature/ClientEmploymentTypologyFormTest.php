<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Models\TipoProdotto;
use Unico\Core\Models\TipoProdottoSub;

class ClientEmploymentTypologyFormTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    public function test_form_only_offers_distinct_cession_employment_typologies(): void
    {
        $cession = $this->cessionProduct();
        $otherProduct = TipoProdotto::query()->firstOrCreate(
            ['tipo_prodotto' => 'Prodotto escluso dal test'],
            ['name' => 'Prodotto escluso dal test'],
        );
        TipoProdottoSub::query()
            ->where('tipoprodotto_id', $cession->id)
            ->delete();
        TipoProdottoSub::query()->insert([
            ['tipoprodotto_id' => $cession->id, 'name' => 'Statale'],
            ['tipoprodotto_id' => $cession->id, 'name' => 'Statale'],
            ['tipoprodotto_id' => $cession->id, 'name' => 'Pensionato INPS'],
            ['tipoprodotto_id' => $otherProduct->id, 'name' => 'Prestito escluso'],
        ]);
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertSchemaComponentExists(
                'employment_typology',
                checkComponentUsing: fn ($component): bool => $component->getOptions() === [
                    'Pensionato INPS' => 'Pensionato INPS',
                    'Statale' => 'Statale',
                ],
            );
    }

    public function test_hiring_date_is_required_for_a_non_retired_person(): void
    {
        $this->createCessionTypology('Statale');
        $client = $this->makeClient(['employment_typology' => 'Statale']);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldVisible('hiring_date')
            ->fillForm(['hiring_date' => null])
            ->call('save')
            ->assertHasFormErrors(['hiring_date' => 'required']);
    }

    public function test_selecting_a_retired_typology_hides_and_clears_hiring_date(): void
    {
        $this->createCessionTypology('Pensionato INPDAP');
        $client = $this->makeClient([
            'employment_typology' => 'Statale',
            'hiring_date' => '2020-01-15',
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm(['employment_typology' => 'Pensionato INPDAP'])
            ->assertFormFieldHidden('hiring_date')
            ->assertFormSet([
                'employment_typology' => 'Pensionato INPDAP',
                'hiring_date' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_hiring_date_is_hidden_for_a_company(): void
    {
        $client = $this->makeClient([
            'is_person' => false,
            'employment_typology' => 'Statale',
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldHidden('hiring_date');
    }

    private function makeClient(array $attributes = []): Client
    {
        return Client::query()->create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ], $attributes));
    }

    private function createCessionTypology(string $name): void
    {
        TipoProdottoSub::query()->firstOrCreate([
            'tipoprodotto_id' => $this->cessionProduct()->id,
            'name' => $name,
        ]);
    }

    private function cessionProduct(): TipoProdotto
    {
        return TipoProdotto::query()->firstOrCreate(
            ['tipo_prodotto' => 'Cessione'],
            ['name' => 'Cessione del Quinto'],
        );
    }
}

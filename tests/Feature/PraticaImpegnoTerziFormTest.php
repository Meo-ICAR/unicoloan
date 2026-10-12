<?php

namespace Tests\Feature;

use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\Praticas\RelationManagers\PraticaClientsRelationManager;
use App\Filament\Resources\Praticas\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Resources\Praticas\RelationManagers\RequisitiOperativiRelationManager;
use App\Filament\Resources\Praticas\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Models\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PraticaImpegnoTerziFormTest extends TestCase
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

    private function pratica(array $attributes = []): Pratica
    {
        return Pratica::create(array_merge([
            'codice_pratica' => 'P-'.Str::upper(Str::random(8)),
            'cognome_cliente' => 'Rossi',
            'nome_cliente' => 'Mario',
            'stato_pratica' => 'IN LAVORAZIONE',
            'is_notowned' => false,
        ], $attributes));
    }

    public function test_impegno_di_terzi_keeps_only_rata_nrate_and_decorrenza(): void
    {
        $pratica = $this->pratica(['is_notowned' => true]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSuccessful()
            ->assertFormFieldIsVisible('is_notowned')
            ->assertFormFieldIsVisible('rata')
            ->assertFormFieldIsVisible('nrate')
            ->assertFormFieldIsVisible('data_inserimento_pratica')
            ->assertFormFieldIsHidden('tipo_prodotto')
            ->assertFormFieldIsHidden('denominazione_prodotto')
            ->assertFormFieldIsHidden('erogato')
            ->assertFormFieldIsHidden('amount')
            ->assertFormFieldIsHidden('net')
            ->assertFormFieldIsHidden('sended_at')
            ->assertFormFieldIsHidden('approved_at')
            ->assertFormFieldIsHidden('erogated_at')
            ->assertFormFieldIsHidden('rejected_at')
            ->assertSee('Data decorrenza');
    }

    public function test_toggling_impegno_di_terzi_hides_product_and_timeline_fields(): void
    {
        $pratica = $this->pratica();

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertFormFieldIsVisible('tipo_prodotto')
            ->assertFormFieldIsVisible('erogated_at')
            ->fillForm(['is_notowned' => true])
            ->assertFormFieldIsHidden('tipo_prodotto')
            ->assertFormFieldIsHidden('erogato')
            ->assertFormFieldIsHidden('amount')
            ->assertFormFieldIsHidden('erogated_at')
            ->assertFormFieldIsVisible('rata')
            ->assertFormFieldIsVisible('nrate')
            ->assertSee('Data decorrenza');
    }

    public function test_impegno_di_terzi_keeps_only_the_documents_relation_manager(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $thirdParty = $this->pratica(['is_notowned' => true]);
        $owned = $this->pratica(['is_notowned' => false, 'tipo_prodotto' => 'Mutuo']);

        $this->assertFalse(PraticaClientsRelationManager::canViewForRecord($thirdParty, EditPratica::class));
        $this->assertFalse(RequisitiOperativiRelationManager::canViewForRecord($thirdParty, EditPratica::class));
        $this->assertFalse(StatusHistoryRelationManager::canViewForRecord($thirdParty, EditPratica::class));
        $this->assertFalse(ProvvigioniRelationManager::canViewForRecord($thirdParty, EditPratica::class));
        $this->assertTrue(DocumentsRelationManager::canViewForRecord($thirdParty, EditPratica::class));

        $this->assertTrue(RequisitiOperativiRelationManager::canViewForRecord($owned, EditPratica::class));
        $this->assertTrue(StatusHistoryRelationManager::canViewForRecord($owned, EditPratica::class));
        $this->assertTrue(ProvvigioniRelationManager::canViewForRecord($owned, EditPratica::class));
        $this->assertTrue(DocumentsRelationManager::canViewForRecord($owned, EditPratica::class));
    }
}

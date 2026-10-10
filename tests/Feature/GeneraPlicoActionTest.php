<?php

namespace Tests\Feature;

use Unico\Core\Enums\DocumentStatus;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Pratica;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GeneraPlicoActionTest extends TestCase
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

    private function type(string $name): DocumentType
    {
        return DocumentType::query()->create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5), 'nature' => 'incoming', 'is_practice' => true, 'is_client' => true]);
    }

    private function plico(array $attributes, array $types): Task
    {
        $task = Task::create(array_merge(['name' => 'Plico '.Str::random(6), 'is_active' => true], $attributes));
        $task->documentTypes()->attach(collect($types)->mapWithKeys(fn (array $row) => [$row[0]->getKey() => ['is_required' => $row[1], 'slug' => Str::random(8)]])->all());

        return $task;
    }

    private function pratica(string $product, string $state): Pratica
    {
        return Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'P-'.Str::random(5), 'tipo_prodotto' => $product, 'stato_pratica' => $state]);
    }

    private function manager(Pratica|Client $owner)
    {
        return Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditPratica::class]);
    }

    public function test_it_creates_the_missing_documents_of_the_plichi_matching_product_and_state(): void
    {
        $cid = $this->type('Carta identità');
        $busta = $this->type('Busta paga');
        $perizia = $this->type('Perizia');
        $pratica = $this->pratica('MUTUO', 'deliberata');
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'DELIBERATA'], [[$cid, true], [$busta, false]]);
        $other = $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Prestito'], [[$perizia, true]]);

        $this->manager($pratica)
            ->callAction(TestAction::make('generaPlico')->table(), ['plichi' => Task::getAvailableFor($pratica)->pluck('id')->all()])
            ->assertNotified('2 documenti richiesti creati');

        $documents = Document::query()->where('documentable_type', 'pratica')->where('documentable_id', $pratica->getKey())->get();
        $this->assertEqualsCanonicalizing([$cid->id, $busta->id], $documents->pluck('document_type_id')->all());
        $this->assertSame([DocumentStatus::PENDING->value], $documents->pluck('status')->map(fn ($status) => $status instanceof \BackedEnum ? $status->value : $status)->unique()->values()->all());
        $this->assertFalse($documents->contains('document_type_id', $perizia->id));
        $this->assertFalse((bool) $documents->first()->is_signed, 'il documento non nasce firmato');
        $this->assertNotNull($other);
    }

    public function test_running_it_again_does_not_duplicate_documents(): void
    {
        $cid = $this->type('Carta identità');
        $pratica = $this->pratica('Mutuo', 'INSERITA');
        $task = $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo'], [[$cid, true]]);

        foreach ([1, 2] as $ignored) {
            $this->manager($pratica)->callAction(TestAction::make('generaPlico')->table(), ['plichi' => [$task->id]]);
        }

        $this->assertSame(1, Document::query()->where('documentable_id', $pratica->getKey())->count());
    }

    public function test_with_no_applicable_plico_it_warns_and_creates_nothing(): void
    {
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo'], [[$this->type('Doc'), true]]);
        $pratica = $this->pratica('Prestito', 'INSERITA');

        $this->manager($pratica)
            ->mountAction(TestAction::make('generaPlico')->table())
            ->assertNotified('Nessun plico applicabile')
            ->assertActionNotMounted();

        $this->assertSame(0, Document::query()->where('documentable_id', $pratica->getKey())->count());
    }

    public function test_client_plico_uses_the_client_trigger_field(): void
    {
        $doc = $this->type('Questionario');
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'status' => 'approvata']);
        $task = $this->plico(['taskable' => 'client', 'trigger_field' => 'status', 'trigger_state' => 'equals', 'trigger_value' => 'approvata'], [[$doc, true]]);

        $this->manager($client)->callAction(TestAction::make('generaPlico')->table(), ['plichi' => [$task->id]])->assertNotified('1 documenti richiesti creati');

        $this->assertSame(1, $client->documents()->where('document_type_id', $doc->id)->count());
    }

    public function test_new_plico_is_created_on_creation_and_on_state_change_without_deleting_the_old_one(): void
    {
        $cid = $this->type('Carta identità');
        $delibera = $this->type('Delibera banca');
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'INSERITA'], [[$cid, true]]);
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'DELIBERATA'], [[$delibera, true]]);

        $pratica = $this->pratica('Mutuo', 'INSERITA');
        $types = fn () => Document::query()->where('documentable_id', $pratica->getKey())->pluck('document_type_id')->all();

        $this->assertSame([$cid->id], $types(), 'alla creazione nasce il plico dello stato iniziale');

        $pratica->update(['stato_pratica' => 'DELIBERATA']);
        $this->assertEqualsCanonicalizing([$cid->id, $delibera->id], $types(), 'al cambio stato si aggiunge il nuovo plico e si tiene il vecchio');

        $pratica->update(['codice_pratica' => 'ALTRO']);
        $this->assertCount(2, $types(), 'un campo non rilevante non genera nulla');
    }

    public function test_filter_shows_only_documents_of_the_plico_of_the_current_state(): void
    {
        $cid = $this->type('Carta identità');
        $delibera = $this->type('Delibera banca');
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'INSERITA'], [[$cid, true]]);
        $this->plico(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'DELIBERATA'], [[$delibera, true]]);
        $pratica = $this->pratica('Mutuo', 'INSERITA');
        $pratica->update(['stato_pratica' => 'DELIBERATA']);
        $old = $pratica->documents()->where('document_type_id', $cid->id)->sole();
        $current = $pratica->documents()->where('document_type_id', $delibera->id)->sole();

        $this->manager($pratica)
            ->assertCanSeeTableRecords([$old, $current])
            ->filterTable('plico_attuale')
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$old]);
    }
}

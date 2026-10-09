<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Agenti\Resources\Pratiche\Pages\CreatePraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPraticheAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\DocumentiFirmabiliRelationManager;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AgentPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private User $agentUser;

    private Fornitore $fornitore;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->agentUser, $this->fornitore] = $this->agent('ZZ AGENTE UNO', '99900000001');
        Filament::setCurrentPanel('agenti');
        $this->actingAs($this->agentUser);
    }

    /**
     * @return array{0: User, 1: Fornitore}
     */
    private function agent(string $name, string $piva): array
    {
        $user = User::factory()->create(['role' => UserRole::AGENT->value]);
        $fornitore = Fornitore::create(['id' => (string) Str::uuid(), 'name' => $name, 'piva' => $piva, 'user_id' => $user->id]);

        return [$user, $fornitore];
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
            'partita_iva_agente' => $this->fornitore->piva,
            'data_inserimento_pratica' => today(),
        ]);
    }

    private function documentType(bool $signed): DocumentType
    {
        return DocumentType::query()->create([
            'name' => ($signed ? 'ZZ Firmabile ' : 'ZZ Libero ').Str::random(4),
            'slug' => 'zz-'.Str::random(8),
            'nature' => 'template_fillable',
            'is_signed' => $signed,
            'is_monitored' => false,
        ]);
    }

    public function test_panel_access_is_limited_to_agents_linked_to_a_fornitore(): void
    {
        $agenti = filament()->getPanel('agenti');
        $admin = filament()->getPanel('admin');

        $this->assertTrue($this->agentUser->canAccessPanel($agenti));
        $this->assertFalse($this->agentUser->canAccessPanel($admin));

        $unlinked = User::factory()->create(['role' => UserRole::AGENT->value]);
        $this->assertFalse($unlinked->canAccessPanel($agenti));

        $employee = User::factory()->create(['role' => UserRole::USER->value]);
        $this->assertFalse($employee->canAccessPanel($agenti));
    }

    public function test_agents_cannot_open_the_admin_panel_over_http(): void
    {
        $this->get('/admin/praticas')->assertForbidden();
    }

    public function test_list_shows_only_own_open_pratiche(): void
    {
        $own = $this->pratica();
        $rejected = $this->pratica(['rejected_at' => today()]);
        $thirdParty = $this->pratica(['is_notowned' => true]);
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $foreign = $this->pratica(['partita_iva_agente' => $other->piva]);

        Livewire::test(ListPraticheAgente::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$rejected, $thirdParty, $foreign]);
    }

    public function test_default_filter_hides_pratiche_erogated_more_than_45_days_ago(): void
    {
        $notErogated = $this->pratica();
        $recent = $this->pratica(['erogated_at' => now()->subDays(45)]);
        $old = $this->pratica(['erogated_at' => now()->subDays(46)]);

        Livewire::test(ListPraticheAgente::class)
            ->assertCanSeeTableRecords([$notErogated, $recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_removing_the_default_filter_shows_older_erogated_pratiche_but_never_rejected_ones(): void
    {
        $old = $this->pratica(['erogated_at' => now()->subDays(90)]);
        $rejected = $this->pratica(['rejected_at' => today(), 'erogated_at' => now()->subDays(90)]);

        Livewire::test(ListPraticheAgente::class)
            ->removeTableFilter('recent_erogation')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$rejected]);
    }

    public function test_creating_a_pratica_assigns_it_to_the_agent_with_initial_state(): void
    {
        Clienti::create(['name' => 'ZZ BANCA', 'principal_type' => 'banca']);
        $product = Tipoprodotto::query()->whereNotNull('tipo_prodotto')->first();

        Livewire::test(CreatePraticaAgente::class)
            ->fillForm([
                'nome_cliente' => 'Mario',
                'cognome_cliente' => 'ZZ Rossi',
                'codice_fiscale' => 'ZZZMRA80A01H501A',
                'tipo_prodotto' => $product->name,
                'denominazione_banca' => 'ZZ BANCA',
                'amount' => 15000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pratica = Pratica::query()->where('cognome_cliente', 'ZZ Rossi')->sole();
        $this->assertSame('INSERITA', $pratica->stato_pratica);
        $this->assertSame($this->fornitore->piva, $pratica->partita_iva_agente);
        $this->assertSame('ZZ AGENTE UNO', $pratica->denominazione_agente);
        $this->assertStringStartsWith('AG-', $pratica->codice_pratica);
        $this->assertTrue($pratica->data_inserimento_pratica->isToday());
        $this->assertFalse((bool) $pratica->is_notowned);
    }

    public function test_creating_a_pratica_requires_the_main_fields(): void
    {
        Livewire::test(CreatePraticaAgente::class)
            ->fillForm(['nome_cliente' => null, 'cognome_cliente' => null, 'codice_fiscale' => null, 'denominazione_banca' => null, 'amount' => null])
            ->call('create')
            ->assertHasFormErrors(['nome_cliente' => 'required', 'cognome_cliente' => 'required', 'codice_fiscale' => 'required', 'denominazione_banca' => 'required', 'amount' => 'required']);
    }

    public function test_agent_cannot_open_the_pratica_of_another_agent(): void
    {
        [, $other] = $this->agent('ZZ AGENTE DUE', '99900000002');
        $foreign = $this->pratica(['partita_iva_agente' => $other->piva]);

        $this->get(route('filament.agenti.resources.pratiche.view', ['record' => $foreign->getKey()]))->assertNotFound();
    }

    public function test_documents_manager_offers_only_signable_types_and_stores_the_upload(): void
    {
        $pratica = $this->pratica();
        $signable = $this->documentType(true);
        $free = $this->documentType(false);

        $component = Livewire::test(DocumentiFirmabiliRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => ViewPraticaAgente::class]);

        $component
            ->callAction(TestAction::make('create')->table(), [
                'document_type_id' => $signable->getKey(),
                'attachments' => UploadedFile::fake()->create('firmato.pdf', 20, 'application/pdf'),
            ])
            ->assertHasNoFormErrors();

        $document = Document::query()->where('documentable_id', $pratica->getKey())->sole();
        $this->assertSame($signable->getKey(), $document->document_type_id);
        $this->assertSame($signable->name, $document->name);
        $this->assertSame($this->agentUser->id, (int) $document->uploaded_by);
        $this->assertCount(1, $document->getMedia('documents'));

        $component->assertCanSeeTableRecords([$document]);
        $this->assertNotContains($free->getKey(), DocumentType::query()->where('is_signed', true)->pluck('id')->all());
    }

    public function test_non_signable_type_is_rejected_when_forced(): void
    {
        $pratica = $this->pratica();
        $free = $this->documentType(false);

        Livewire::test(DocumentiFirmabiliRelationManager::class, ['ownerRecord' => $pratica, 'pageClass' => ViewPraticaAgente::class])
            ->callAction(TestAction::make('create')->table(), [
                'document_type_id' => $free->getKey(),
                'attachments' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
            ]);

        $this->assertSame(0, Document::query()->where('documentable_id', $pratica->getKey())->count());
    }
}

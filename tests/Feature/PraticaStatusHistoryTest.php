<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\PraticaStatusHistory;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PraticaStatusHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function pratica(string $stato = 'INSERITA'): Pratica
    {
        return Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'ZZ-'.Str::random(6), 'stato_pratica' => $stato, 'nome_cliente' => 'Mario', 'cognome_cliente' => 'ZZ Rossi']);
    }

    private function history(Pratica $pratica): Collection
    {
        return PraticaStatusHistory::query()->where('pratica_id', $pratica->getKey())->orderBy('id')->get();
    }

    public function test_creation_logs_the_initial_state_without_a_user_when_unauthenticated(): void
    {
        $entries = $this->history($this->pratica());

        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->status_from);
        $this->assertSame('INSERITA', $entries[0]->status_to);
        $this->assertSame('sistema', $entries[0]->source);
        $this->assertNull($entries[0]->user_id);
    }

    public function test_state_change_is_logged_with_date_user_and_note(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($user);
        $pratica = $this->pratica();

        $pratica->pendingStatusNote = 'Richiesta integrazione busta paga';
        $pratica->update(['stato_pratica' => 'SOSPESA']);

        $entry = $this->history($pratica)->last();
        $this->assertSame('INSERITA', $entry->status_from);
        $this->assertSame('SOSPESA', $entry->status_to);
        $this->assertSame('Richiesta integrazione busta paga', $entry->notes);
        $this->assertSame($user->id, (int) $entry->user_id);
        $this->assertSame('manuale', $entry->source);
        $this->assertTrue($entry->changed_at->isToday());
        $this->assertSame($user->name, $entry->user->name);
        $this->assertNull($pratica->pendingStatusNote);
    }

    public function test_a_note_without_a_state_change_is_logged_on_its_own(): void
    {
        $this->actingAs(User::factory()->create());
        $pratica = $this->pratica();

        $pratica->pendingStatusNote = 'Cliente richiamato';
        $pratica->update(['codice_pratica' => 'ZZ-ALTRO']);

        $entry = $this->history($pratica)->last();
        $this->assertSame('INSERITA', $entry->status_from);
        $this->assertSame('INSERITA', $entry->status_to);
        $this->assertSame('Cliente richiamato', $entry->notes);
    }

    public function test_saving_without_changes_or_notes_logs_nothing(): void
    {
        $pratica = $this->pratica();

        $pratica->update(['codice_pratica' => 'ZZ-ALTRO']);
        $pratica->update(['stato_pratica' => 'INSERITA']);

        $this->assertCount(1, $this->history($pratica));
    }

    public function test_agent_changes_are_logged_with_the_portal_source(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::AGENT->value]));

        $this->assertSame('portale', $this->history($this->pratica())->sole()->source);
    }

    public function test_edit_form_saves_the_note_and_shows_the_history_panel(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->create(['role' => UserRole::ADMIN->value, 'name' => 'Istruttore Zeta']);
        $this->actingAs($user);
        $pratica = $this->pratica();

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->fillForm(['stato_pratica' => 'SOSPESA', 'status_note' => 'Manca il documento originale'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSee('Manca il documento originale');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertSee('Storico stati e annotazioni')
            ->assertSee('INSERITA → SOSPESA')
            ->assertSee('Manca il documento originale')
            ->assertSee('Istruttore Zeta');

        $entry = $this->history($pratica)->last();
        $this->assertSame('SOSPESA', $entry->status_to);
        $this->assertSame('Manca il documento originale', $entry->notes);
        $this->assertSame('SOSPESA', $pratica->fresh()->stato_pratica);
    }
}

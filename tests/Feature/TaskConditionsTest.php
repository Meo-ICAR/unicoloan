<?php

namespace Tests\Feature;

use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TaskConditionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function task(array $attributes): Task
    {
        return Task::create(array_merge(['name' => 'Plico '.Str::random(6), 'is_active' => true], $attributes));
    }

    private function pratica(string $product, string $state): Pratica
    {
        return new Pratica(['tipo_prodotto' => $product, 'stato_pratica' => $state]);
    }

    public function test_pratica_task_matches_product_and_state(): void
    {
        $task = $this->task(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'DELIBERATA']);

        $this->assertTrue($task->matchesConditions($this->pratica('Mutuo', 'DELIBERATA')));
        $this->assertTrue($task->matchesConditions($this->pratica('MUTUO', 'deliberata')), 'il confronto non distingue maiuscole');
        $this->assertFalse($task->matchesConditions($this->pratica('Mutuo', 'INSERITA')));
        $this->assertFalse($task->matchesConditions($this->pratica('Prestito', 'DELIBERATA')));
    }

    public function test_subfield_alone_and_missing_subfield_behave_sensibly(): void
    {
        $onlyState = $this->task(['taskable' => 'pratica', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'APPROVATA']);
        $onlyProduct = $this->task(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Prestito']);

        $this->assertTrue($onlyState->matchesConditions($this->pratica('Qualsiasi', 'APPROVATA')));
        $this->assertFalse($onlyState->matchesConditions($this->pratica('Qualsiasi', 'INSERITA')));
        $this->assertTrue($onlyProduct->matchesConditions($this->pratica('Prestito', 'QUALSIASI')));
    }

    public function test_client_task_needs_only_the_trigger_field(): void
    {
        $task = $this->task(['taskable' => 'client', 'trigger_field' => 'status', 'trigger_state' => 'equals', 'trigger_value' => 'approvata']);

        $this->assertTrue($task->matchesConditions(new Client(['status' => 'approvata'])));
        $this->assertFalse($task->matchesConditions(new Client(['status' => 'raccolta_dati'])));
    }

    public function test_exclusion_still_wins_and_available_tasks_are_found_by_model_name(): void
    {
        $task = $this->task(['taskable' => 'pratica', 'trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'exclude_field' => 'stato_pratica', 'exclude_state' => 'equals', 'exclude_value' => 'RINUNCIA CLIENTE']);

        $this->assertTrue(Task::getAvailableFor($this->pratica('Mutuo', 'INSERITA'))->contains($task));
        $this->assertFalse(Task::getAvailableFor($this->pratica('Mutuo', 'RINUNCIA CLIENTE'))->contains($task));
        $this->assertFalse(Task::getAvailableFor(new Client(['status' => 'x']))->contains($task));
    }

    public function test_pratica_and_client_are_in_the_morph_map_and_found_by_alias(): void
    {
        $task = $this->task(['taskable' => 'client']);

        $this->assertSame('client', (new Client)->getMorphClass());
        $this->assertSame('pratica', (new Pratica)->getMorphClass());
        $this->assertTrue(Task::getAvailableFor(new Client)->contains($task));
    }

    public function test_task_form_saves_the_second_condition_and_offers_pratica_and_client(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $task = $this->task(['taskable' => 'pratica']);

        Livewire::test(EditTask::class, ['record' => $task->getKey()])
            ->assertSchemaComponentExists('taskable', checkComponentUsing: fn ($c) => array_key_exists('pratica', $c->getOptions()) && array_key_exists('client', $c->getOptions()))
            ->fillForm(['trigger_field' => 'tipo_prodotto', 'trigger_state' => 'equals', 'trigger_value' => 'Mutuo', 'trigger_subfield' => 'stato_pratica', 'trigger_subvalue' => 'DELIBERATA'])
            ->call('save')
            ->assertHasNoFormErrors();

        $task->refresh();
        $this->assertSame(['stato_pratica', 'DELIBERATA'], [$task->trigger_subfield, $task->trigger_subvalue]);
    }
}

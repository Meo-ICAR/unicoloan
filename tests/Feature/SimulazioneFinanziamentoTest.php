<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Agenti\Pages\SimulazioneFinanziamento;
use App\Models\Fornitore;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SimulazioneFinanziamentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-13 10:00:00');

        $user = User::factory()->create(['role' => UserRole::AGENT->value]);
        Fornitore::create(['name' => 'ZZ AGENTE SIMULAZIONE', 'piva' => '99900000099', 'user_id' => $user->id]);

        Filament::setCurrentPanel('agenti');
        $this->actingAs($user);
    }

    public function test_agent_opens_the_wizard_with_decorrenza_three_months_from_today(): void
    {
        $this->get(SimulazioneFinanziamento::getUrl())->assertOk();

        Livewire::test(SimulazioneFinanziamento::class)
            ->assertSchemaStateSet(['data_decorrenza' => '2027-01-13'], 'form');
    }

    public function test_employee_profile_requires_hiring_date_and_reports_age_and_seniority_at_decorrenza(): void
    {
        $page = Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'F',
                'data_nascita' => '1980-02-20',
                'tipo_rapporto' => 'statale',
                'data_assunzione' => null,
            ])
            ->call('simulate')
            ->assertHasFormErrors(['data_assunzione' => 'required']);

        $page->fillForm(['data_assunzione' => '2015-06-01'])
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('profilo.eta_decorrenza', 46)
            ->assertSet('profilo.anzianita_mesi', 139)
            ->assertSet('profilo.data_decorrenza', '01-13-2027')
            ->assertSee('11 anni e 7 mesi');
    }

    public function test_retiree_profile_skips_hiring_date(): void
    {
        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm([
                'tipo_contratto' => 'prestito',
                'sesso' => 'M',
                'data_nascita' => '1955-03-01',
                'tipo_rapporto' => 'pensionato_inps',
            ])
            ->assertFormFieldHidden('data_assunzione')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('profilo.tipo_rapporto', 'Pensionato INPS')
            ->assertSet('profilo.anzianita_mesi', null);
    }

    public function test_delega_is_not_available_to_retirees(): void
    {
        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'M',
                'data_nascita' => '1955-03-01',
                'tipo_rapporto' => 'pensionato_inpdap',
            ])
            ->fillForm(['tipo_contratto' => 'delega'])
            ->assertSchemaStateSet(['tipo_rapporto' => null], 'form')
            ->fillForm(['tipo_rapporto' => 'pensionato_inpdap'])
            ->call('simulate')
            ->assertHasFormErrors(['tipo_rapporto' => 'in'])
            ->assertSet('profilo', null);
    }
}

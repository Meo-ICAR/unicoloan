<?php

namespace Tests\Feature;

use App\Enums\SimulationContractType;
use App\Enums\UserRole;
use App\Filament\Agenti\Pages\SimulazioneFinanziamento;
use App\Models\FinancingQuote;
use App\Models\Fornitore;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Wizard;
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

    public function test_administration_and_hiring_date_are_on_the_first_step_and_amount_on_the_second(): void
    {
        $component = Livewire::test(SimulazioneFinanziamento::class);

        $wizard = $component->instance()->form->getComponent(
            fn (mixed $child): bool => $child instanceof Wizard,
        );
        $steps = $wizard->getDefaultChildComponents();

        $names = function (mixed $step): array {
            $children = $step->getDefaultChildComponents();

            return collect(is_array($children) ? $children : $children->getComponents())
                ->map(fn (mixed $field): ?string => method_exists($field, 'getName') ? $field->getName() : null)
                ->filter()
                ->values()
                ->all();
        };

        $first = $names($steps[0]);
        $second = $names($steps[1]);

        $this->assertContains('tipo_rapporto', $first);
        $this->assertContains('data_assunzione', $first);
        $this->assertNotContains('durata', $first);
        $this->assertSame(['durata', 'quota_cedibile', 'importo_richiesto', 'data_decorrenza'], $second);
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
                'durata' => 120,
                'quota_cedibile' => 280,
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
            ->assertSet('profilo.durata_mesi', 120)
            ->assertSet('profilo.quota_cedibile', 280.0)
            ->assertSet('profilo.importo_richiesto', null)
            ->assertSee('11 anni e 7 mesi')
            ->assertSee('120 mesi')
            ->assertSee('280,00');
    }

    public function test_retiree_profile_skips_hiring_date(): void
    {
        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm([
                'tipo_contratto' => 'prestito',
                'durata' => 84,
                'importo_richiesto' => 15000,
                'sesso' => 'M',
                'data_nascita' => '1955-03-01',
                'tipo_rapporto' => 'pensionato_inps',
            ])
            ->assertFormFieldHidden('data_assunzione')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('profilo.tipo_rapporto', 'Pensionato INPS')
            ->assertSet('profilo.anzianita_mesi', null)
            ->assertSet('profilo.durata_mesi', 84)
            ->assertSet('profilo.importo_richiesto', 15000.0)
            ->assertSet('profilo.quota_cedibile', null)
            ->assertSee('15.000,00');
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

    public function test_cessione_and_delega_ask_assignable_quota_while_prestito_asks_requested_amount(): void
    {
        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm(['tipo_contratto' => 'cessione'])
            ->assertFormFieldVisible('durata')
            ->assertFormFieldVisible('quota_cedibile')
            ->assertFormFieldHidden('importo_richiesto')
            ->fillForm(['tipo_contratto' => 'delega'])
            ->assertFormFieldVisible('quota_cedibile')
            ->assertFormFieldHidden('importo_richiesto')
            ->fillForm([
                'tipo_contratto' => 'cessione',
                'sesso' => 'F',
                'data_nascita' => '1980-02-20',
                'tipo_rapporto' => 'statale',
                'data_assunzione' => '2015-06-01',
                'durata' => 60,
                'quota_cedibile' => null,
            ])
            ->call('simulate')
            ->assertHasFormErrors(['quota_cedibile' => 'required'])
            ->fillForm(['tipo_contratto' => 'prestito', 'durata' => 60])
            ->assertFormFieldHidden('quota_cedibile')
            ->assertFormFieldVisible('importo_richiesto')
            ->call('simulate')
            ->assertHasFormErrors(['importo_richiesto' => 'required']);
    }

    public function test_duration_choices_follow_the_contract_and_cessione_defaults_to_sixty_months(): void
    {
        $this->assertSame([24, 36, 48, 60, 72, 84, 96, 108, 120], SimulationContractType::Cessione->durationChoices());
        $this->assertSame(SimulationContractType::Cessione->durationChoices(), SimulationContractType::Delega->durationChoices());
        $this->assertSame(
            array_merge([12], SimulationContractType::Cessione->durationChoices()),
            SimulationContractType::Prestito->durationChoices(),
        );
        $this->assertSame(range(5, 30), SimulationContractType::Mutuo->durationChoices());
        $this->assertSame(60, SimulationContractType::Cessione->defaultDuration());
        $this->assertNull(SimulationContractType::Mutuo->defaultDuration());

        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm(['tipo_contratto' => 'cessione'])
            ->assertSchemaStateSet(['durata' => 60], 'form')
            ->fillForm(['tipo_contratto' => 'delega'])
            ->assertSchemaStateSet(['durata' => 60], 'form')
            ->fillForm(['tipo_contratto' => 'prestito'])
            ->assertSchemaStateSet(['durata' => 60], 'form')
            ->fillForm($this->cessioneProfile())
            ->set('data.tipo_contratto', 'prestito')
            ->set('data.durata', 12)
            ->set('data.importo_richiesto', 8000)
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('profilo.durata_mesi', 12)
            ->assertSet('profilo.durata_etichetta', '12 mesi');

        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm([
                'tipo_contratto' => 'mutuo',
                'sesso' => 'M',
                'data_nascita' => '1985-04-01',
                'tipo_rapporto' => 'privato_spa',
                'data_assunzione' => '2010-01-01',
                'durata' => 20,
                'importo_richiesto' => 180000,
            ])
            ->assertFormFieldHidden('quota_cedibile')
            ->call('simulate')
            ->assertHasNoFormErrors()
            ->assertSet('profilo.durata_mesi', 240)
            ->assertSet('profilo.durata_etichetta', '20 anni')
            ->assertSee('20 anni');
    }

    public function test_saving_a_quote_assigns_the_next_identifier_of_that_producer(): void
    {
        $page = Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm($this->cessioneProfile())
            ->call('simulate')
            ->assertSee('Memorizzo preventivo')
            ->call('memorizzaPreventivo')
            ->assertSet('preventivoIdentificativo', 1)
            ->assertSee('Identificativo del produttore')
            ->assertDontSee('Memorizzo preventivo');

        $quote = FinancingQuote::query()->sole();
        $this->assertSame(1, $quote->number);
        $this->assertSame('cessione', $quote->contract_type);
        $this->assertSame('280.00', $quote->assignable_quota);
        $this->assertNull($quote->requested_amount);

        $page->fillForm(['durata' => 84])
            ->call('simulate')
            ->assertSee('Memorizzo preventivo')
            ->call('memorizzaPreventivo')
            ->assertSet('preventivoIdentificativo', 2);

        $other = User::factory()->create(['role' => UserRole::AGENT->value]);
        Fornitore::create(['name' => 'ZZ ALTRO PRODUTTORE', 'piva' => '99900000098', 'user_id' => $other->id]);
        $this->actingAs($other);

        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm($this->cessioneProfile())
            ->call('simulate')
            ->call('memorizzaPreventivo')
            ->assertSet('preventivoIdentificativo', 1);
    }

    public function test_declining_the_quote_does_not_store_it(): void
    {
        Livewire::test(SimulazioneFinanziamento::class)
            ->fillForm($this->cessioneProfile())
            ->call('simulate')
            ->call('scartaPreventivo')
            ->assertSet('preventivoDeciso', true)
            ->assertSet('preventivoIdentificativo', null)
            ->assertDontSee('Memorizzo preventivo');

        $this->assertSame(0, FinancingQuote::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function cessioneProfile(): array
    {
        return [
            'tipo_contratto' => 'cessione',
            'sesso' => 'F',
            'data_nascita' => '1980-02-20',
            'tipo_rapporto' => 'statale',
            'data_assunzione' => '2015-06-01',
            'durata' => 120,
            'quota_cedibile' => 280,
        ];
    }
}

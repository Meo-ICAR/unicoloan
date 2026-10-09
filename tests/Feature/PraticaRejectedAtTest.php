<?php

namespace Tests\Feature;

use App\Models\PraticaStato;
use App\Models\PROFORMA\Pratica;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PraticaRejectedAtTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        PraticaStato::create(['codice' => 'declinata', 'name' => 'Declinata', 'ordine' => 1, 'is_rejected' => true]);
        PraticaStato::create(['codice' => 'inserita', 'name' => 'Inserita', 'ordine' => 2, 'is_rejected' => false]);
    }

    private function pratica(?string $stato): Pratica
    {
        return Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'ZZ-'.Str::random(6), 'stato_pratica' => $stato]);
    }

    public function test_rejected_state_sets_rejected_at_on_create(): void
    {
        $pratica = $this->pratica('DECLINATA');

        $this->assertNotNull($pratica->fresh()->rejected_at);
        $this->assertTrue($pratica->fresh()->rejected_at->isToday());
    }

    public function test_changing_to_a_rejected_state_sets_rejected_at(): void
    {
        $pratica = $this->pratica('INSERITA');
        $this->assertNull($pratica->fresh()->rejected_at);

        $pratica->update(['stato_pratica' => 'DECLINATA']);

        $this->assertNotNull($pratica->fresh()->rejected_at);
    }

    public function test_state_match_ignores_case_and_works_with_the_code(): void
    {
        $this->assertNotNull($this->pratica('DECLINATA')->fresh()->rejected_at);

        PraticaStato::query()->where('codice', 'declinata')->update(['name' => 'Altro nome']);
        $this->assertNotNull($this->pratica('DECLINATA')->fresh()->rejected_at);
    }

    public function test_non_rejected_or_unknown_states_leave_rejected_at_empty(): void
    {
        $this->assertNull($this->pratica('INSERITA')->fresh()->rejected_at);
        $this->assertNull($this->pratica('NOTIFICA')->fresh()->rejected_at);
        $this->assertNull($this->pratica(null)->fresh()->rejected_at);
    }

    public function test_existing_rejected_at_is_not_overwritten(): void
    {
        $pratica = $this->pratica('INSERITA');
        $pratica->update(['rejected_at' => '2026-01-15']);

        $pratica->update(['stato_pratica' => 'DECLINATA']);

        $this->assertSame('2026-01-15', $pratica->fresh()->rejected_at->toDateString());
    }

    public function test_saving_without_changing_the_state_does_not_set_rejected_at(): void
    {
        $pratica = $this->pratica('INSERITA');
        PraticaStato::query()->where('codice', 'inserita')->update(['is_rejected' => true]);

        $pratica->update(['codice_pratica' => 'ZZ-ALTRO']);

        $this->assertNull($pratica->fresh()->rejected_at);
    }
}

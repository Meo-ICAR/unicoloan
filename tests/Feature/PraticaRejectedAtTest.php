<?php

namespace Tests\Feature;

use App\Models\PROFORMA\Pratica;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Usa gli stati reali di `pratiches_statos` (colonna isrejected): DECLINATA, PERIZIA KO, PRATICA RESPINTA e RINUNCIA CLIENTE sono rifiuti.
 */
class PraticaRejectedAtTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

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

    public function test_every_rejection_state_of_the_catalog_sets_rejected_at(): void
    {
        foreach (['DECLINATA', 'PERIZIA KO', 'PRATICA RESPINTA', 'RINUNCIA CLIENTE'] as $stato) {
            $this->assertNotNull($this->pratica($stato)->fresh()->rejected_at, $stato);
        }
    }

    public function test_changing_to_a_rejected_state_sets_rejected_at(): void
    {
        $pratica = $this->pratica('INSERITA');
        $this->assertNull($pratica->fresh()->rejected_at);

        $pratica->update(['stato_pratica' => 'DECLINATA']);

        $this->assertNotNull($pratica->fresh()->rejected_at);
    }

    public function test_state_match_ignores_case(): void
    {
        $this->assertNotNull($this->pratica('declinata')->fresh()->rejected_at);
    }

    public function test_non_rejected_or_blank_states_leave_rejected_at_empty(): void
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

        $pratica->update(['codice_pratica' => 'ZZ-ALTRO']);

        $this->assertNull($pratica->fresh()->rejected_at);
    }
}

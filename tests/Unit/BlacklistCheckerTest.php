<?php

namespace Tests\Unit;

use App\Models\BlacklistClienteEmployee;
use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Services\BlacklistChecker;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class BlacklistCheckerTest extends TestCase
{
    public function test_blank_agent_or_bank_name_is_never_blacklisted(): void
    {
        $checker = new BlacklistChecker;

        $this->assertFalse($checker->isAgenteNameBlacklistedForBancaName(null, 'Banca Test'));
        $this->assertFalse($checker->isAgenteNameBlacklistedForBancaName('Agente Test', null));
        $this->assertFalse($checker->isAgenteNameBlacklistedForBancaName('', ''));
    }

    public function test_unknown_agent_or_bank_name_is_never_blacklisted(): void
    {
        $checker = new BlacklistChecker;

        $this->assertFalse($checker->isAgenteNameBlacklistedForBancaName(
            'Agente Che Non Esiste '.uniqid(),
            'Banca Che Non Esiste '.uniqid(),
        ));
    }

    /**
     * Employee had no blacklist concept at all before this feature; this locks in the
     * new relation so it can't silently regress back to "not implemented".
     */
    public function test_employee_banche_blacklist_relation_targets_the_pivot_model(): void
    {
        $employee = new Employee;

        $relation = $employee->bancheBlacklist();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(BlacklistClienteEmployee::class, $relation->getRelated());
    }

    public function test_clienti_dipendenti_blacklistati_relation_targets_the_pivot_model(): void
    {
        $clienti = new Clienti;

        $relation = $clienti->dipendentiBlacklistati();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(BlacklistClienteEmployee::class, $relation->getRelated());
    }
}

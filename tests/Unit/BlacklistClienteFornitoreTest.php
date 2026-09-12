<?php

namespace Tests\Unit;

use App\Models\BlacklistClienteFornitore;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class BlacklistClienteFornitoreTest extends TestCase
{
    /**
     * These relations previously referenced an undefined `App\Models\Cliente` class
     * (the real bank model is `App\Models\PROFORMA\Clienti`), which threw a fatal
     * "Class not found" error as soon as the relation was resolved.
     */
    public function test_blacklist_relations_resolve_to_the_correct_models(): void
    {
        $blacklist = new BlacklistClienteFornitore;

        $this->assertInstanceOf(BelongsTo::class, $blacklist->cliente());
        $this->assertInstanceOf(Clienti::class, $blacklist->cliente()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $blacklist->fornitore());
        $this->assertInstanceOf(Fornitore::class, $blacklist->fornitore()->getRelated());
    }

    public function test_fornitore_banche_blacklist_relation_resolves_to_clienti(): void
    {
        $fornitore = new Fornitore;

        $relation = $fornitore->bancheBlacklist();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Clienti::class, $relation->getRelated());
    }
}

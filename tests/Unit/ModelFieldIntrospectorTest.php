<?php

namespace Tests\Unit;

use App\Services\ModelFieldIntrospector;
use InvalidArgumentException;
use Tests\TestCase;

class ModelFieldIntrospectorTest extends TestCase
{
    public function test_non_lookup_fields_pass_through_unchanged(): void
    {
        $introspector = new ModelFieldIntrospector;

        $this->assertSame('un valore qualsiasi', $introspector->resolveFieldValue('fornitore', 'description', 'un valore qualsiasi'));
        $this->assertNull($introspector->resolveFieldValue('fornitore', 'description', null));
    }

    public function test_throws_when_a_lookup_value_cannot_be_resolved(): void
    {
        $introspector = new ModelFieldIntrospector;

        $this->expectException(InvalidArgumentException::class);

        $introspector->resolveFieldValue('pratica', 'denominazione_banca', 'Banca che non esiste '.uniqid());
    }

    public function test_throws_for_an_unknown_model_type(): void
    {
        $introspector = new ModelFieldIntrospector;

        $this->expectException(InvalidArgumentException::class);

        $introspector->columns('modello-sconosciuto');
    }
}

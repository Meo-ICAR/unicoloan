<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModelFieldsApiControllerTest extends TestCase
{
    public function test_lists_columns_with_comments_for_a_known_model(): void
    {
        $response = $this->getJson('/api/models/fornitore/fields');

        $response->assertOk();
        $response->assertJsonStructure(['model', 'columns', 'lookups']);
        $response->assertJsonFragment(['name' => 'stipulated_at']);
    }

    public function test_returns_404_for_an_unknown_model(): void
    {
        $response = $this->getJson('/api/models/unknown-model/fields');

        $response->assertNotFound();
    }

    public function test_lists_belongs_to_relations_as_lookups_for_pratica(): void
    {
        $response = $this->getJson('/api/models/pratica/fields');

        $response->assertOk();
        $response->assertJsonFragment([
            'relation' => 'agente',
            'foreign_key' => 'partita_iva_agente',
            'owner_key' => 'piva',
            'related_model' => 'fornitore',
        ]);
    }
}

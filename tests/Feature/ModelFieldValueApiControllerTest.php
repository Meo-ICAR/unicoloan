<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModelFieldValueApiControllerTest extends TestCase
{
    public function test_rejects_a_field_that_does_not_exist_on_the_model(): void
    {
        $response = $this->patchJson('/api/models/fornitore/'.fake()->uuid(), [
            'field' => 'campo_inesistente',
            'value' => 'x',
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_404_for_an_unknown_model(): void
    {
        $response = $this->patchJson('/api/models/unknown-model/'.fake()->uuid(), [
            'field' => 'name',
            'value' => 'x',
        ]);

        $response->assertNotFound();
    }

    public function test_returns_404_when_the_record_does_not_exist(): void
    {
        $response = $this->patchJson('/api/models/fornitore/'.fake()->uuid(), [
            'field' => 'description',
            'value' => 'x',
        ]);

        $response->assertNotFound();
        $response->assertJson(['message' => 'Record non trovato.']);
    }
}

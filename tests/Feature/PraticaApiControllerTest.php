<?php

namespace Tests\Feature;

use Tests\TestCase;

class PraticaApiControllerTest extends TestCase
{
    public function test_returns_404_for_an_unknown_pratica(): void
    {
        $response = $this->getJson('/api/pratiche/'.fake()->uuid());

        $response->assertNotFound();
        $response->assertJson(['message' => 'Pratica non trovata.']);
    }
}

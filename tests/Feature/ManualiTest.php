<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ManualiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_functions_manual_is_served_to_logged_in_users_only(): void
    {
        $this->get('/manuali/funzioni')->assertRedirect();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/manuali/funzioni')
            ->assertOk()
            ->assertSee('Funzioni dell\'applicazione', false);
    }

    public function test_unknown_manual_is_a_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/manuali/inesistente')->assertNotFound();
    }
}

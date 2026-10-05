<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualiRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/manuali/utente');

        $response->assertRedirect();
    }

    #[DataProvider('manualProvider')]
    public function test_authenticated_user_can_view_manual(string $slug): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get("/manuali/{$slug}");

        $response->assertOk();
    }

    public function test_unknown_manual_slug_returns_404(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/manuali/inesistente');

        $response->assertNotFound();
    }

    public static function manualProvider(): array
    {
        return [
            'utente' => ['utente'],
            'admin' => ['admin'],
            'tecnico' => ['tecnico'],
        ];
    }
}

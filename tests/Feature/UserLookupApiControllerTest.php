<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserLookupApiControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reports_existence_for_a_known_user_email(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);

        $response = $this->getJson('/api/users/lookup?email='.$user->email);

        $response->assertOk();
        $response->assertJson(['exists' => true]);
    }

    public function test_reports_no_match_for_an_unknown_email(): void
    {
        $response = $this->getJson('/api/users/lookup?email=nobody@example.com');

        $response->assertOk();
        $response->assertJson(['exists' => false]);
    }
}

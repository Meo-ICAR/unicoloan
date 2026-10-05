<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CheckPianoHelperTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_full_plan_grants_every_feature_for_default_user(): void
    {
        config()->set('plan.type', 'full');
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $this->assertTrue(checkPiano('audits'));
        $this->assertTrue(checkPiano('qualunque-cosa'));
    }

    public function test_role_without_feature_is_denied_even_on_full_plan(): void
    {
        config()->set('plan.type', 'full');
        $this->actingAs(User::factory()->create(['role' => 'quality']));

        // "quality" ha solo audits e document-relation-manager.
        $this->assertTrue(checkPiano('audits'));
        $this->assertFalse(checkPiano('employees'));
    }

    public function test_result_is_memoized_per_request_and_keyed_by_plan(): void
    {
        $user = User::factory()->create(['role' => 'inspector']);
        $this->actingAs($user);

        config()->set('plan.type', 'full');
        $this->assertFalse(checkPiano('employees'));

        // Cambiando la chiave (piano) il risultato viene ricalcolato, non servito
        // dalla cache della chiamata precedente.
        config()->set('plan.type', 'debug');
        $this->assertFalse(checkPiano('employees'));
    }
}

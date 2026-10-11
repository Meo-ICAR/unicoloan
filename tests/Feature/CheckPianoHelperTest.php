<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Unico\Core\Access\AccessChecker;

class CheckPianoHelperTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_full_plan_grants_every_feature_for_default_user(): void
    {
        config()->set('unico-core.access.plan', 'full');
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $this->assertTrue(checkPiano('audits'));
        $this->assertTrue(checkPiano('qualunque-cosa'));
    }

    public function test_the_agent_role_never_passes_through_the_panel_features(): void
    {
        config()->set('unico-core.access.plan', 'full');
        $this->actingAs(User::factory()->create(['role' => 'agent']));

        $this->assertFalse(checkPiano('audits'));
    }

    public function test_admin_sees_everything(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertTrue(checkPiano('employees'));
    }

    public function test_result_is_memoized_per_request_and_keyed_by_plan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $access = app(AccessChecker::class);

        config()->set('unico-core.access.plan', 'full');
        $this->assertTrue(checkPiano('employees'));

        // Cambiando il piano il risultato viene ricalcolato, non servito dalla cache della chiamata precedente.
        config()->set('unico-core.access.plan', 'base');
        $this->assertSame(\Unico\Core\Access\PlanType::Base, $access->plan());
        $this->assertTrue(checkPiano('employees'));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InspectorReadOnlyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inspector_sees_the_resource_but_cannot_change_it(): void
    {
        config()->set('unico-core.access.plan', 'full');
        $this->actingAs(User::factory()->create(['role' => 'inspector']));

        $this->assertTrue(ClientResource::canViewAny());
        $this->assertFalse(ClientResource::canCreate());
        $this->assertFalse(checkPiano('clients', null, true));
    }

    public function test_inspector_stays_within_the_company_plan(): void
    {
        config()->set('unico-core.access.plan', 'base');
        Resource::create(['app_name' => config('app.name'), 'key' => 'premium-zz', 'name' => 'Premium', 'min_plan' => 'FULL']);
        $this->actingAs(User::factory()->create(['role' => 'inspector']));

        $this->assertFalse(checkPiano('premium-zz'));
    }

    public function test_a_normal_user_can_still_write(): void
    {
        config()->set('unico-core.access.plan', 'full');
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $this->assertTrue(ClientResource::canCreate());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Fornitores\Pages\EditFornitore;
use App\Filament\Resources\Fornitores\Pages\ListFornitores;
use App\Models\Fornitore;
use App\Models\User;
use App\Services\Agenti\AgentImpersonationService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AgentImpersonationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fornitore(array $attributes = []): Fornitore
    {
        return Fornitore::create($attributes + [
            'name' => 'ZZ AGENTE '.Str::random(4),
            'piva' => '999'.random_int(10000000, 99999999),
            'email' => 'zz.'.Str::lower(Str::random(6)).'@example.test',
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Fornitore}
     */
    private function adminWithEnabledFornitore(): array
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agent = User::factory()->create(['role' => UserRole::AGENT->value]);
        $fornitore = $this->fornitore(['user_id' => $agent->id]);

        return [$admin, $agent, $fornitore];
    }

    public function test_admin_can_impersonate_an_enabled_fornitore_and_reach_agenti_panel(): void
    {
        [$admin, $agent, $fornitore] = $this->adminWithEnabledFornitore();

        $this->actingAs($admin)
            ->withSession([])
            ->get('/admin');

        $url = app(AgentImpersonationService::class)->start($admin, $fornitore);

        $this->assertSame($agent->id, auth()->id());
        $this->assertTrue(app(AgentImpersonationService::class)->isImpersonating());
        $this->assertTrue(str_contains($url, '/agenti'));
        $this->get($url)->assertOk();
    }

    public function test_leave_restores_the_admin_and_returns_to_fornitore_edit(): void
    {
        [$admin, $agent, $fornitore] = $this->adminWithEnabledFornitore();

        $this->actingAs($admin)
            ->withSession([])
            ->get('/admin');

        app(AgentImpersonationService::class)->start($admin, $fornitore);

        $response = $this->post(route('agenti.impersonation.leave'));

        $response->assertRedirect();
        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(app(AgentImpersonationService::class)->isImpersonating());
        $this->assertTrue(str_contains($response->headers->get('Location'), '/admin/'));
        $this->assertTrue(str_contains($response->headers->get('Location'), (string) $fornitore->getKey()));
    }

    public function test_impersonation_is_rejected_without_agent_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $fornitore = $this->fornitore(['user_id' => null]);
        $this->actingAs($admin);

        $this->assertFalse(app(AgentImpersonationService::class)->canImpersonate($admin, $fornitore));
        $this->expectException(\RuntimeException::class);
        app(AgentImpersonationService::class)->start($admin, $fornitore);
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER->value]);
        $agent = User::factory()->create(['role' => UserRole::AGENT->value]);
        $fornitore = $this->fornitore(['user_id' => $agent->id]);
        $this->actingAs($user);

        $this->assertFalse(app(AgentImpersonationService::class)->canImpersonate($user, $fornitore));
    }

    public function test_table_action_is_visible_only_for_admins_on_enabled_fornitori(): void
    {
        Filament::setCurrentPanel('admin');
        [$admin, $agent, $enabled] = $this->adminWithEnabledFornitore();
        $disabled = $this->fornitore(['user_id' => null]);

        $this->actingAs($admin);

        Livewire::test(ListFornitores::class)
            ->assertCanSeeTableRecords([$enabled, $disabled])
            ->assertActionVisible(TestAction::make('impersonateFornitore')->table($enabled))
            ->assertActionHidden(TestAction::make('impersonateFornitore')->table($disabled));

        $this->actingAs(User::factory()->create(['role' => UserRole::USER->value]));

        Livewire::test(ListFornitores::class)
            ->assertActionHidden(TestAction::make('impersonateFornitore')->table($enabled));
    }

    public function test_edit_page_action_impersonates_and_redirects_to_agenti(): void
    {
        Filament::setCurrentPanel('admin');
        [$admin, $agent, $fornitore] = $this->adminWithEnabledFornitore();
        $this->actingAs($admin);

        Livewire::test(EditFornitore::class, ['record' => $fornitore->getKey()])
            ->callAction('impersonateFornitore')
            ->assertRedirect(Filament::getPanel('agenti')->getUrl());

        $this->assertSame($agent->id, auth()->id());
        $this->assertTrue(app(AgentImpersonationService::class)->isImpersonating());
    }

    public function test_leave_route_is_forbidden_when_not_impersonating(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin);

        $this->post(route('agenti.impersonation.leave'))->assertForbidden();
    }
}

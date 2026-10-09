<?php

namespace Tests\Feature;

use App\Enums\AgentAccessOutcome;
use App\Enums\UserRole;
use App\Filament\Resources\Fornitores\Pages\ListFornitores;
use App\Models\PROFORMA\Fornitore;
use App\Models\User;
use App\Services\Agenti\AgentAccessService;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AgentAccessTest extends TestCase
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
            'id' => (string) Str::uuid(),
            'name' => 'ZZ AGENTE '.Str::random(4),
            'piva' => '999'.random_int(10000000, 99999999),
            'email' => 'zz.'.Str::lower(Str::random(6)).'@example.test',
        ]);
    }

    public function test_enable_creates_an_agent_user_links_it_and_sends_the_password_email(): void
    {
        Notification::fake();
        $fornitore = $this->fornitore(['email' => ' ZZ.Agente@Example.Test ']);

        $outcome = app(AgentAccessService::class)->enable($fornitore);

        $this->assertSame(AgentAccessOutcome::Created, $outcome);
        $user = User::query()->where('email', 'zz.agente@example.test')->sole();
        $this->assertSame(UserRole::AGENT->value, $user->role);
        $this->assertSame($fornitore->name, $user->name);
        $this->assertSame($user->id, (int) $fornitore->fresh()->user_id);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('agenti')));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return str_contains($notification->url, '/agenti/')
                && str_contains($notification->url, urlencode($user->email));
        });
    }

    public function test_enable_skips_already_enabled_fornitori(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => UserRole::AGENT->value]);
        $fornitore = $this->fornitore(['user_id' => $user->id]);

        $this->assertSame(AgentAccessOutcome::AlreadyEnabled, app(AgentAccessService::class)->enable($fornitore));
        Notification::assertNothingSent();
    }

    public function test_enable_skips_missing_or_invalid_emails(): void
    {
        Notification::fake();

        $this->assertSame(AgentAccessOutcome::MissingEmail, app(AgentAccessService::class)->enable($this->fornitore(['email' => null])));
        $this->assertSame(AgentAccessOutcome::MissingEmail, app(AgentAccessService::class)->enable($this->fornitore(['email' => 'non-una-email'])));
        Notification::assertNothingSent();
    }

    public function test_enable_never_takes_over_an_existing_user_with_the_same_email(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'zz.esistente@example.test', 'role' => UserRole::ADMIN->value]);
        $fornitore = $this->fornitore(['email' => 'ZZ.Esistente@example.test']);

        $this->assertSame(AgentAccessOutcome::EmailInUse, app(AgentAccessService::class)->enable($fornitore));
        $this->assertNull($fornitore->fresh()->user_id);
        $this->assertSame(UserRole::ADMIN->value, $existing->fresh()->role);
        Notification::assertNothingSent();
    }

    public function test_enabling_twice_creates_a_single_user(): void
    {
        Notification::fake();
        $fornitore = $this->fornitore();

        app(AgentAccessService::class)->enable($fornitore);
        $this->assertSame(AgentAccessOutcome::AlreadyEnabled, app(AgentAccessService::class)->enable($fornitore->fresh()));
        $this->assertSame(1, User::query()->where('email', Str::lower($fornitore->email))->count());
    }

    public function test_bulk_action_enables_the_selected_fornitori_for_admins(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN->value]));
        $withEmail = $this->fornitore();
        $withoutEmail = $this->fornitore(['email' => null]);

        Livewire::test(ListFornitores::class)
            ->selectTableRecords([$withEmail->getKey(), $withoutEmail->getKey()])
            ->callAction(TestAction::make('createAgentAccess')->table()->bulk())
            ->assertNotified('Accessi al portale agenti');

        $this->assertNotNull($withEmail->fresh()->user_id);
        $this->assertNull($withoutEmail->fresh()->user_id);
    }

    public function test_bulk_action_is_hidden_for_non_admin_users(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::USER->value]));
        $fornitore = $this->fornitore();

        Livewire::test(ListFornitores::class)
            ->selectTableRecords([$fornitore->getKey()])
            ->assertActionHidden(TestAction::make('createAgentAccess')->table()->bulk());
    }
}

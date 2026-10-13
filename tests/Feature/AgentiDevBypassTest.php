<?php

namespace Tests\Feature;

use App\Filament\Agenti\Pages\SimulazioneFinanziamento;
use App\Models\User;
use App\Support\Agenti\AgentiDevBypass;
use App\Support\Agenti\DevCampanellaIdentity;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AgentiDevBypassTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agenti.dev_bypass.enabled', false);
    }

    private function enableBypassInLocal(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('agenti.dev_bypass.enabled', true);
    }

    public function test_dev_login_is_not_available_when_flag_is_off_even_in_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->get(route('agenti.dev.login'))->assertNotFound();
    }

    public function test_dev_login_is_not_available_in_testing_even_when_flag_is_on(): void
    {
        config()->set('agenti.dev_bypass.enabled', true);

        $this->get(route('agenti.dev.login'))->assertNotFound();
    }

    public function test_dev_login_is_not_available_in_production_even_when_flag_is_on(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('agenti.dev_bypass.enabled', true);

        $this->get(route('agenti.dev.login'))->assertNotFound();
    }

    public function test_dev_login_authenticates_and_redirects_to_simulation_wizard_in_local(): void
    {
        $this->enableBypassInLocal();
        DevCampanellaIdentity::ensure();

        $response = $this->get(route('agenti.dev.login'));

        $response->assertRedirect(SimulazioneFinanziamento::getUrl(panel: 'agenti'));
        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('agenti')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_dev_login_responds_clearly_when_identity_is_not_prepared(): void
    {
        $this->enableBypassInLocal();

        $this->get(route('agenti.dev.login'))
            ->assertServiceUnavailable()
            ->assertSee('agenti:ensure-dev-campanella', false);
    }

    public function test_guest_visiting_simulation_wizard_is_sent_to_dev_login_when_bypass_is_enabled(): void
    {
        $this->enableBypassInLocal();
        DevCampanellaIdentity::ensure();

        $this->get(SimulazioneFinanziamento::getUrl(panel: 'agenti'))
            ->assertRedirect(route('agenti.dev.login'));
    }

    public function test_guest_visiting_simulation_wizard_is_sent_to_agenti_login_when_bypass_is_disabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->get(SimulazioneFinanziamento::getUrl(panel: 'agenti'))
            ->assertRedirect(route('filament.agenti.auth.login'));
    }

    public function test_guest_on_agenti_area_is_not_sent_to_admin_login(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->get('/agenti/pratiche')
            ->assertRedirect(route('filament.agenti.auth.login'));
    }

    public function test_authenticated_dev_user_is_denied_admin_panel_over_http(): void
    {
        $this->enableBypassInLocal();
        DevCampanellaIdentity::ensure();

        $this->get(route('agenti.dev.login'));

        $this->get('/admin/praticas')->assertForbidden();
    }

    public function test_is_enabled_requires_local_environment_and_config_flag(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('agenti.dev_bypass.enabled', true);
        $this->assertTrue(AgentiDevBypass::isEnabled());

        config()->set('agenti.dev_bypass.enabled', false);
        $this->assertFalse(AgentiDevBypass::isEnabled());

        $this->app->detectEnvironment(fn (): string => 'testing');
        config()->set('agenti.dev_bypass.enabled', true);
        $this->assertFalse(AgentiDevBypass::isEnabled());
    }
}

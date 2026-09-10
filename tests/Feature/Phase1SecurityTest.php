<?php

namespace Tests\Feature;

use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1SecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_socialite_user_without_password_gets_random_password(): void
    {
        $user = new User(['name' => 'Social', 'email' => 'social@example.com']);
        $user->save();

        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_can_access_panel_respects_allowed_email_domains(): void
    {
        config()->set('panel.allowed_email_domains', ['races.it']);

        $allowed = User::factory()->create(['email' => 'mario@races.it']);
        $blocked = User::factory()->make(['email' => 'intruso@gmail.com']);
        $blocked->created_at = now();

        $panel = filament()->getPanel('admin');

        $this->assertTrue($allowed->canAccessPanel($panel));
        $this->assertFalse($blocked->canAccessPanel($panel));
    }

    public function test_empty_allowed_domains_keeps_open_access(): void
    {
        config()->set('panel.allowed_email_domains', []);

        $user = User::factory()->make(['email' => 'anyone@anywhere.tld']);

        $this->assertTrue($user->canAccessPanel(filament()->getPanel('admin')));
    }

    public function test_plan_feature_gate_reads_config_not_env(): void
    {
        config()->set('plan.type', 'full');

        $this->assertTrue(checkPiano('audits'));
    }

    public function test_restricted_role_cannot_access_resource_by_authorization_method(): void
    {
        $inspector = User::factory()->create(['role' => 'inspector']);
        $this->actingAs($inspector);

        // L'ispettore ha "audits" fra le sue feature ma non "employees".
        $this->assertTrue(AuditResource::canViewAny());
        $this->assertFalse(EmployeeResource::canViewAny());
        $this->assertFalse(EmployeeResource::canCreate());
    }

    public function test_document_download_requires_authentication(): void
    {
        $company = Company::factory()->create();
        $document = Document::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'documentable_type' => 'company',
            'documentable_id' => $company->id,
            'name' => 'Allegato riservato',
            'status' => 'verified',
        ]);

        $this->get(route('documents.download', $document))
            ->assertRedirect(); // guest -> redirect al login

        $this->actingAs(User::factory()->create());

        // Autenticato: nessun media allegato -> 404, ma non piu' redirect anonimo.
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_bpm_landing_validates_required_parameters(): void
    {
        $this->get('/bpm-landing/123')->assertStatus(302); // manca token/user_email
    }
}

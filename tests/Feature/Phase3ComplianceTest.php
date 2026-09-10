<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use App\Models\ComplaintRegistry;
use Database\Seeders\AuditSeeder;
use Database\Seeders\ComplaintRegistrySeeder;
use Database\Seeders\ResourceSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Phase3ComplianceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_gets_sequential_protocol_number_per_year(): void
    {
        $company = Company::factory()->create();

        $a = Audit::factory()->create(['company_id' => $company->id, 'auditable_id' => $company->id, 'protocol_number' => null]);
        $b = Audit::factory()->create(['company_id' => $company->id, 'auditable_id' => $company->id, 'protocol_number' => null]);

        $year = now()->year;
        $this->assertSame("{$year}/0001", $a->protocol_number);
        $this->assertSame("{$year}/0002", $b->protocol_number);
    }

    public function test_complaint_registry_gets_protocol_number_and_uses_fk_columns(): void
    {
        $company = Company::factory()->create();

        $complaint = ComplaintRegistry::factory()->create([
            'company_id' => $company->id,
            'protocol_number' => null,
            'agent_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'bank_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ]);

        $this->assertSame(now()->year.'/0001', $complaint->protocol_number);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $complaint->agent()->getForeignKeyName() === 'agent_id' ? $complaint->agent_id : null);
        $this->assertSame('bank_id', $complaint->bank()->getForeignKeyName());
    }

    public function test_compliance_models_write_activity_log(): void
    {
        $company = Company::factory()->create();

        $audit = Audit::factory()->create(['company_id' => $company->id, 'auditable_id' => $company->id]);
        AuditFinding::factory()->create(['audit_id' => $audit->id, 'company_id' => $company->id]);
        ComplaintRegistry::factory()->create(['company_id' => $company->id]);

        $this->assertTrue(Activity::query()->where('log_name', 'audits')->exists());
        $this->assertTrue(Activity::query()->where('log_name', 'audit_findings')->exists());
        $this->assertTrue(Activity::query()->where('log_name', 'complaint_registry')->exists());
    }

    public function test_activity_retention_default_is_ten_years(): void
    {
        $this->assertGreaterThanOrEqual(3650, (int) config('activitylog.clean_after_days'));
    }

    public function test_seeders_run_without_error(): void
    {
        Company::factory()->create();

        $this->seed([ResourceSeeder::class, AuditSeeder::class, ComplaintRegistrySeeder::class]);

        $this->assertDatabaseHas('resources', ['key' => 'complaint-registries']);
        $this->assertTrue(Audit::query()->count() >= 6);
        $this->assertTrue(ComplaintRegistry::query()->count() >= 8);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AuditSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first() ?? Company::factory()->create();

        if (Audit::query()->exists()) {
            return;
        }

        Audit::factory()
            ->count(4)
            ->conforme()
            ->create(['company_id' => $company->id, 'auditable_id' => $company->id]);

        Audit::factory()
            ->count(2)
            ->conRilievi()
            ->create(['company_id' => $company->id, 'auditable_id' => $company->id])
            ->each(function (Audit $audit) use ($company): void {
                AuditFinding::factory()
                    ->count(2)
                    ->create(['audit_id' => $audit->id, 'company_id' => $company->id]);
            });
    }
}

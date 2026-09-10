<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ComplaintRegistry;
use Illuminate\Database\Seeder;

class ComplaintRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first() ?? Company::factory()->create();

        if (ComplaintRegistry::query()->exists()) {
            return;
        }

        ComplaintRegistry::factory()->count(5)->create(['company_id' => $company->id]);
        ComplaintRegistry::factory()->count(2)->resolved()->create(['company_id' => $company->id]);
        ComplaintRegistry::factory()->count(1)->overdue()->create(['company_id' => $company->id]);
    }
}

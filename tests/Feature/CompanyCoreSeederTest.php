<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRole;
use Database\Seeders\CompanyRoleSeeder;
use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyCoreSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_company_seeder_persists_races_finance_on_core_connection(): void
    {
        $coreConnection = config('unico-core.connection');

        $this->seed(CompanySeeder::class);

        $this->assertDatabaseHas('companies', [
            'vat_number' => '05822361007',
            'name' => 'Races Finance S.r.l.',
        ], $coreConnection);
    }

    public function test_company_role_seeder_links_roles_to_company_on_core_connection(): void
    {
        $coreConnection = config('unico-core.connection');

        $this->seed(CompanySeeder::class);
        $this->seed(CompanyRoleSeeder::class);

        $companyId = Company::query()->where('vat_number', '05822361007')->value('id');
        $this->assertNotNull($companyId);

        $this->assertSame(4, CompanyRole::query()->where('company_id', $companyId)->count());

        $rolesOnCore = DB::connection($coreConnection)
            ->table('company_roles')
            ->where('company_id', $companyId)
            ->count();
        $this->assertSame(4, $rolesOnCore);

        $companyExistsOnCore = DB::connection($coreConnection)
            ->table('companies')
            ->where('id', $companyId)
            ->exists();
        $this->assertTrue($companyExistsOnCore);
    }
}

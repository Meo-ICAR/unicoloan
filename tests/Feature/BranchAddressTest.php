<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchAddressTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_branch_stores_address_fields(): void
    {
        $company = Company::factory()->create();

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'address' => 'Via Ferrante Imparato',
            'street_number' => '190',
            'city' => 'Napoli',
            'zip_code' => '80146',
            'province' => 'Napoli',
            'region' => 'Campania',
        ]);

        $this->assertModelExists($branch);
        $this->assertSame('Via Ferrante Imparato', $branch->address);
        $this->assertSame('190', $branch->street_number);
        $this->assertSame('Napoli', $branch->city);
        $this->assertSame('80146', $branch->zip_code);
        $this->assertSame('Napoli', $branch->province);
        $this->assertSame('Campania', $branch->region);
    }

    public function test_branch_address_fields_are_nullable(): void
    {
        $company = Company::factory()->create();

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'address' => null,
            'street_number' => null,
            'city' => null,
            'zip_code' => null,
            'province' => null,
            'region' => null,
        ]);

        $this->assertModelExists($branch);
        $this->assertNull($branch->address);
        $this->assertNull($branch->city);
    }

    public function test_branch_main_office_factory_state(): void
    {
        $company = Company::factory()->create();

        $branch = Branch::factory()->mainOffice()->create([
            'company_id' => $company->id,
        ]);

        $this->assertTrue($branch->is_main_office);
    }
}

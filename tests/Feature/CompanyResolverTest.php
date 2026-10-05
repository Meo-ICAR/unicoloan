<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\CompanyResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CompanyResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resolver_returns_first_company(): void
    {
        $company = Company::factory()->create(['name' => 'Races Finance SRL']);

        $resolver = new CompanyResolver;
        $resolved = $resolver->resolve();

        $this->assertSame($company->id, $resolved->id);
        $this->assertSame('Races Finance SRL', $resolved->name);
    }

    public function test_resolve_id_returns_uuid_string(): void
    {
        $company = Company::factory()->create();

        $resolver = new CompanyResolver;
        $id = $resolver->resolveId();

        $this->assertSame($company->id, $id);
        $this->assertIsString($id);
    }

    public function test_resolver_throws_when_no_company_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Nessuna Company configurata/');

        $resolver = new CompanyResolver;
        $resolver->resolve();
    }

    public function test_resolver_returns_first_when_multiple_companies_exist(): void
    {
        $first = Company::factory()->create(['name' => 'Prima Company']);
        Company::factory()->create(['name' => 'Seconda Company']);

        $resolver = new CompanyResolver;
        $resolved = $resolver->resolve();

        $this->assertSame($first->id, $resolved->id);
    }
}

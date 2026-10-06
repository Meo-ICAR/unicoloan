<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleDataResolverClientLookupTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    public function test_finds_the_client_by_tax_code(): void
    {
        $code = strtoupper(Str::random(16));
        $client = Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);

        $found = (new ModuleDataResolver)->findClient(new Pratica(['codice_fiscale' => $code]));

        $this->assertTrue($found->is($client));
    }

    public function test_finds_a_company_by_vat_number_when_the_pratica_carries_the_vat_number(): void
    {
        $vat = (string) random_int(10000000000, 99999999999);
        $client = Client::create(['name' => 'Acme Spa', 'is_person' => false, 'vat_number' => $vat]);

        $found = (new ModuleDataResolver)->findClient(new Pratica(['codice_fiscale' => $vat]));

        $this->assertTrue($found->is($client));
    }

    public function test_returns_null_for_blank_or_unknown_codes(): void
    {
        $resolver = new ModuleDataResolver;

        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => null])));
        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => ''])));
        $this->assertNull($resolver->findClient(new Pratica(['codice_fiscale' => 'NONESISTE0000000'])));
    }
}

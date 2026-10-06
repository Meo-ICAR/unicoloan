<?php

namespace Tests\Feature;

use App\Enums\PdfModuleClientScope;
use App\Models\Client;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleSuggester;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModuleSuggesterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_suggests_active_modules_matching_product_and_client_type_sorted_by_name(): void
    {
        PdfModule::factory()->create(['name' => 'B Prestiti retail', 'tipi_prodotto' => ['Prestito'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'A Privacy', 'tipi_prodotto' => null, 'client_scope' => PdfModuleClientScope::Entrambi]);
        PdfModule::factory()->create(['name' => 'C Corporate', 'tipi_prodotto' => ['Prestito'], 'client_scope' => PdfModuleClientScope::PersonaGiuridica]);
        PdfModule::factory()->create(['name' => 'D Mutui', 'tipi_prodotto' => ['Mutuo'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'E Disattivo', 'tipi_prodotto' => null, 'is_active' => false]);

        $suggested = app(ModuleSuggester::class)->suggest(
            new Pratica(['tipo_prodotto' => 'Prestito']),
            new Client(['is_person' => true]),
        );

        $this->assertSame(['A Privacy', 'B Prestiti retail'], $suggested->pluck('name')->all());
    }

    public function test_company_client_gets_corporate_modules(): void
    {
        PdfModule::factory()->create(['name' => 'Retail', 'tipi_prodotto' => ['Aziendale'], 'client_scope' => PdfModuleClientScope::PersonaFisica]);
        PdfModule::factory()->create(['name' => 'Corporate', 'tipi_prodotto' => ['Aziendale'], 'client_scope' => PdfModuleClientScope::PersonaGiuridica]);

        $suggested = app(ModuleSuggester::class)->suggest(
            new Pratica(['tipo_prodotto' => 'Aziendale']),
            new Client(['is_person' => false]),
        );

        $this->assertSame(['Corporate'], $suggested->pluck('name')->all());
    }
}

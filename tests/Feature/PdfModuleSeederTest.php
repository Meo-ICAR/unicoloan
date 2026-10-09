<?php

namespace Tests\Feature;

use App\Models\PdfModule;
use Database\Seeders\PdfModuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PdfModuleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_registers_the_known_modules_and_is_idempotent(): void
    {
        $this->seed(PdfModuleSeeder::class);
        $this->seed(PdfModuleSeeder::class);

        $this->assertSame(13, PdfModule::count());

        $prestiti = PdfModule::where('name', 'like', '%Prestiti Personali%')->sole();
        $this->assertSame(['Prestito', 'CHIROGRAFARIO', 'Microcredito'], $prestiti->tipi_prodotto);
        $this->assertSame('persona_fisica', $prestiti->client_scope->value);

        $corporate = PdfModule::where('name', 'like', '%Corporate%')->sole();
        $this->assertSame('persona_giuridica', $corporate->client_scope->value);
    }

    public function test_seeder_does_not_overwrite_manual_edits(): void
    {
        $this->seed(PdfModuleSeeder::class);

        $module = PdfModule::where('name', 'like', '%Prestiti Personali%')->sole();
        $module->update(['tipi_prodotto' => ['Cessione'], 'is_active' => false]);

        $this->seed(PdfModuleSeeder::class);

        $module->refresh();
        $this->assertSame(['Cessione'], $module->tipi_prodotto);
        $this->assertFalse($module->is_active);
    }
}

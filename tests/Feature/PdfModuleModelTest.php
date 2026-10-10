<?php

namespace Tests\Feature;

use App\Enums\PdfModuleClientScope;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PdfModuleModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_models_live_on_the_default_mysql_connection(): void
    {
        $this->assertSame('mysql', (new PdfModule)->getConnectionName());
        $this->assertSame('mysql', (new PdfModuleField)->getConnectionName());
    }

    public function test_module_casts_and_active_scope(): void
    {
        PdfModule::factory()->create(['tipi_prodotto' => ['Prestito'], 'client_scope' => 'persona_fisica']);
        PdfModule::factory()->create(['is_active' => false]);

        $module = PdfModule::active()->sole();

        $this->assertSame(['Prestito'], $module->tipi_prodotto);
        $this->assertSame(PdfModuleClientScope::PersonaFisica, $module->client_scope);
    }

    public function test_applies_to_checks_product_case_insensitively_and_client_scope(): void
    {
        $module = new PdfModule([
            'tipi_prodotto' => ['Prestito', 'CHIROGRAFARIO'],
            'client_scope' => PdfModuleClientScope::PersonaFisica,
        ]);

        $this->assertTrue($module->appliesTo('prestito', true));
        $this->assertTrue($module->appliesTo('Chirografario', true));
        $this->assertFalse($module->appliesTo('Mutuo', true));
        $this->assertFalse($module->appliesTo('Prestito', false));
        $this->assertFalse($module->appliesTo(null, true));
    }

    public function test_module_without_products_applies_to_any_product(): void
    {
        $module = new PdfModule(['tipi_prodotto' => null, 'client_scope' => PdfModuleClientScope::Entrambi]);

        $this->assertTrue($module->appliesTo('Qualsiasi', true));
        $this->assertTrue($module->appliesTo(null, false));
    }

    public function test_fields_are_unique_per_module_and_cascade_on_delete(): void
    {
        $module = PdfModule::factory()->create();
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'Text1']);

        try {
            PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'Text1']);
            $this->fail('Il vincolo unique (modulo, nome campo) non ha impedito il duplicato.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $module->delete();

        $this->assertDatabaseCount('pdf_module_fields', 0);
    }

    public function test_checkbox_and_referenced_keys_helpers(): void
    {
        $field = new PdfModuleField([
            'pdf_field_type' => 'checkbox',
            'source_key' => 'client.is_person',
            'checkbox_when' => '!client.is_person',
        ]);

        $this->assertTrue($field->isCheckbox());
        $this->assertSame(
            ['client.is_person', 'client.is_person'],
            $field->referencedKeys(),
        );
        $this->assertFalse((new PdfModuleField(['pdf_field_type' => 'text']))->isCheckbox());
        $this->assertSame([], (new PdfModuleField(['pdf_field_type' => 'text']))->referencedKeys());
    }
}

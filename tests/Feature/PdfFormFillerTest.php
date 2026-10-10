<?php

namespace Tests\Feature;

use Unico\Core\Pdf\ModuleFormatter;
use App\Enums\ModuleSourceKey as Key;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PdfFormFiller;
use Unico\Core\Pdf\ResolvedModuleData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfFormFillerTest extends TestCase
{
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function module(array $fields, string $path = 'module/modulo-prova.pdf'): PdfModule
    {
        $module = new PdfModule(['name' => 'Modulo prova', 'file_path' => $path]);
        $module->setRelation('pdfModuleFields', collect(array_map(fn (array $attributes) => new PdfModuleField($attributes), $fields)));

        return $module;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function data(array $values): ResolvedModuleData
    {
        return new ResolvedModuleData($values);
    }

    public function test_build_field_values_formats_text_and_resolves_checkboxes(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNominativo->value, 'formatter' => ModuleFormatter::Upper],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text', 'source_key' => Key::PraticaImporto->value, 'formatter' => ModuleFormatter::MoneyIt],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica->value, 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'non_mappato', 'pdf_field_type' => 'text', 'source_key' => null],
        ]);

        $values = (new PdfFormFiller)->buildFieldValues($module, $this->data([
            Key::ClienteNominativo->value => 'Rossi Mario',
            Key::PraticaImporto->value => '15000.00',
            Key::ClientePersonaFisica->value => true,
        ]));

        $this->assertSame(['cliente' => 'ROSSI MARIO', 'importo' => '15.000,00', 'consenso' => 'si'], $values);
    }

    public function test_checkbox_off_when_falsy_and_negated_condition(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'a', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica->value, 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'b', 'pdf_field_type' => 'checkbox', 'checkbox_when' => '!client.is_person', 'checkbox_on_value' => 'si'],
            ['pdf_field_name' => 'c', 'pdf_field_type' => 'checkbox', 'checkbox_on_value' => 'si'],
        ]);

        $values = (new PdfFormFiller)->buildFieldValues($module, $this->data([Key::ClientePersonaFisica->value => false]));

        $this->assertSame(['a' => 'Off', 'b' => 'si'], $values);
    }

    public function test_empty_values_do_not_overwrite_the_template(): void
    {
        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome->value],
        ]);

        $this->assertSame([], (new PdfFormFiller)->buildFieldValues($module, $this->data([Key::ClienteNome->value => null])));
    }

    public function test_fill_writes_values_into_the_real_pdf(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNominativo->value],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text', 'source_key' => Key::PraticaOggi->value, 'formatter' => ModuleFormatter::DateIt],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'source_key' => Key::ClientePersonaFisica->value, 'checkbox_on_value' => 'si'],
        ]);

        $pdf = (new PdfFormFiller)->fill($module, $this->data([
            Key::ClienteNominativo->value => 'Niccolò D\'Avéna & Figli',
            Key::PraticaOggi->value => Carbon::parse('2026-10-06'),
            Key::ClientePersonaFisica->value => true,
        ]));

        $this->assertStringStartsWith('%PDF', $pdf);

        $fields = $this->readPdfFields($pdf);
        $this->assertSame('Niccolò D\'Avéna & Figli', $fields['cliente']);
        $this->assertSame('06/10/2026', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);
        $this->assertNull($fields['non_mappato']);
    }

    public function test_flatten_removes_the_form_fields(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome->value],
        ]);

        $pdf = (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']), flatten: true);

        $this->assertSame([], $this->readPdfFields($pdf));
    }

    public function test_diagnostic_shows_each_field_name_and_checks_checkboxes(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([
            ['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text'],
            ['pdf_field_name' => 'importo', 'pdf_field_type' => 'text'],
            ['pdf_field_name' => 'consenso', 'pdf_field_type' => 'checkbox', 'checkbox_on_value' => 'si'],
        ]);

        $fields = $this->readPdfFields((new PdfFormFiller)->fillDiagnostic($module));

        $this->assertSame('cliente', $fields['cliente']);
        $this->assertSame('importo', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);
    }

    public function test_store_diagnostic_writes_to_the_public_disk(): void
    {
        $this->skipUnlessPdftk();

        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text']]);

        $path = (new PdfFormFiller)->storeDiagnostic($module);

        $this->assertSame('module-diagnostica/modulo-prova.pdf', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_missing_template_throws(): void
    {
        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome->value]], 'module/inesistente.pdf');

        $this->expectException(PdfFormException::class);
        $this->expectExceptionMessage('module/inesistente.pdf');

        (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']));
    }

    public function test_missing_pdftk_binary_throws_a_dedicated_exception(): void
    {
        config(['unico-core.pdf.pdftk_binary' => '/percorso/inesistente/pdftk']);

        $module = $this->module([['pdf_field_name' => 'cliente', 'pdf_field_type' => 'text', 'source_key' => Key::ClienteNome->value]]);

        $this->expectException(PdfFormException::class);

        (new PdfFormFiller)->fill($module, $this->data([Key::ClienteNome->value => 'Mario']));
    }
}

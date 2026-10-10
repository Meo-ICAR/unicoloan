<?php

namespace Tests\Feature;

use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Services\PdfFieldSynchronizer;
use Unico\Core\Pdf\PdfFormException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfFieldSynchronizerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    public function test_it_discovers_only_pdf_files_in_the_module_directory(): void
    {
        Storage::disk('public')->put('module/appunti.txt', 'x');
        Storage::disk('public')->put('module/altra-cartella/nascosto.pdf', $this->fixturePdfContents());
        Storage::disk('public')->put('altrove.pdf', $this->fixturePdfContents());

        $this->assertSame(['module/modulo-prova.pdf'], app(PdfFieldSynchronizer::class)->discoverFiles());
    }

    public function test_module_name_is_derived_from_the_file_name(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);

        $this->assertSame(
            'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile',
            $synchronizer->moduleNameFromPath('module/Races VERS. 03_2026 - Fascicolo completo retail  CQ compilabile_compressed.pdf'),
        );
    }

    public function test_first_sync_creates_the_module_and_its_fields(): void
    {
        $result = app(PdfFieldSynchronizer::class)->sync('module/modulo-prova.pdf');

        $this->assertSame('modulo-prova', $result['module']->name);
        $this->assertEqualsCanonicalizing(['cliente', 'importo', 'consenso', 'non_mappato'], $result['created']);
        $this->assertSame([], $result['removed']);

        $checkbox = PdfModuleField::where('pdf_field_name', 'consenso')->sole();
        $this->assertSame('checkbox', $checkbox->pdf_field_type);
        $this->assertSame('si', $checkbox->checkbox_on_value);

        $this->assertSame('text', PdfModuleField::where('pdf_field_name', 'cliente')->sole()->pdf_field_type);
    }

    public function test_sync_is_idempotent_and_keeps_manual_mappings(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);
        $synchronizer->sync('module/modulo-prova.pdf');

        PdfModuleField::where('pdf_field_name', 'cliente')->update(['source_key' => 'client.name', 'formatter' => 'upper']);

        $second = $synchronizer->sync('module/modulo-prova.pdf');

        $this->assertSame([], $second['created']);
        $this->assertSame(1, PdfModule::count());
        $this->assertSame(4, PdfModuleField::count());

        $field = PdfModuleField::where('pdf_field_name', 'cliente')->sole();
        $this->assertSame('client.name', $field->source_key);
        $this->assertSame('upper', $field->formatter->value);
    }

    public function test_fields_missing_from_the_pdf_are_reported_but_not_deleted(): void
    {
        $synchronizer = app(PdfFieldSynchronizer::class);
        $module = $synchronizer->sync('module/modulo-prova.pdf')['module'];
        PdfModuleField::create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'vecchio_campo']);

        $result = $synchronizer->sync('module/modulo-prova.pdf');

        $this->assertSame(['vecchio_campo'], $result['removed']);
        $this->assertDatabaseHas('pdf_module_fields', ['pdf_field_name' => 'vecchio_campo']);
    }

    public function test_sync_of_a_missing_file_throws(): void
    {
        $this->expectException(PdfFormException::class);

        app(PdfFieldSynchronizer::class)->sync('module/inesistente.pdf');
    }

    public function test_command_syncs_every_module_and_reports_the_outcome(): void
    {
        $this->artisan('modules:sync-fields')
            ->expectsOutputToContain('modulo-prova: 4 nuovi')
            ->assertExitCode(0);

        $this->assertSame(4, PdfModuleField::count());
    }

    public function test_command_with_diagnostic_writes_a_pdf_showing_the_field_names(): void
    {
        $this->artisan('modules:sync-fields', ['--diagnostic' => true])->assertExitCode(0);

        Storage::disk('public')->assertExists('module-diagnostica/modulo-prova.pdf');

        $fields = $this->readPdfFields(Storage::disk('public')->get('module-diagnostica/modulo-prova.pdf'));
        $this->assertSame('cliente', $fields['cliente']);
        $this->assertSame('si', $fields['consenso']);
    }

    public function test_command_exits_with_failure_when_a_pdf_cannot_be_read(): void
    {
        Storage::disk('public')->put('module/rotto.pdf', 'questo non e un pdf');

        $this->artisan('modules:sync-fields')
            ->expectsOutputToContain('rotto')
            ->assertExitCode(1);

        $this->assertDatabaseHas('pdf_modules', ['file_path' => 'module/modulo-prova.pdf']);
    }
}

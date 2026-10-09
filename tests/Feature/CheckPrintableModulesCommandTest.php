<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Client;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class CheckPrintableModulesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());

        $module = PdfModule::factory()->create(['name' => 'Modulo prova', 'file_path' => 'module/modulo-prova.pdf', 'tipi_prodotto' => ['Prestito']]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente', 'source_key' => Key::ClienteNominativo]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'importo', 'source_key' => Key::ClienteEmail]);
    }

    private function pratica(string $code, ?string $taxCode, string $product = 'Prestito'): Pratica
    {
        return Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => $code, 'codice_fiscale' => $taxCode, 'tipo_prodotto' => $product]);
    }

    public function test_reports_success_when_every_suggested_module_has_all_its_data(): void
    {
        Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTEST80A01H501A', 'email' => 'rossi@example.test']);
        $this->pratica('ZZ-OK', 'ZZTEST80A01H501A');

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-OK']])
            ->expectsOutputToContain('Pratiche controllate: 1 | stampabili in modo completo: 1')
            ->assertExitCode(0);
    }

    public function test_reports_missing_data_per_module_and_key_and_fails(): void
    {
        Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTEST80A01H501A']);
        $this->pratica('ZZ-GAP', 'ZZTEST80A01H501A');

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-GAP']])
            ->expectsOutputToContain('Modulo "Modulo prova": dati mancanti')
            ->expectsOutputToContain('client.email: 1 pratiche (es. ZZ-GAP)')
            ->assertExitCode(1);
    }

    public function test_reports_pratiche_without_client_or_without_suggested_modules(): void
    {
        Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTEST80A01H501A', 'email' => 'rossi@example.test']);
        $this->pratica('ZZ-NOCLIENT', 'ZZTEST99A01H501E');
        $this->pratica('ZZ-NOMODULE', 'ZZTEST80A01H501A', 'Mutuo');

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-NOCLIENT', 'ZZ-NOMODULE']])
            ->expectsOutputToContain('Pratiche senza cliente (codice fiscale/P.IVA non trovato): 1 (es. ZZ-NOCLIENT)')
            ->expectsOutputToContain('Pratiche senza moduli suggeriti: 1 (es. ZZ-NOMODULE (Mutuo))')
            ->assertExitCode(1);
    }

    public function test_ignores_third_party_commitments(): void
    {
        $this->pratica('ZZ-THIRD', 'ZZTEST99A01H501E')->update(['is_notowned' => true]);

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-THIRD']])
            ->expectsOutputToContain('Pratiche controllate: 0')
            ->assertExitCode(0);
    }

    public function test_render_option_actually_fills_the_pdfs(): void
    {
        $this->skipUnlessPdftk();

        Client::create(['name' => 'ZZ ROSSI', 'is_person' => true, 'tax_code' => 'ZZTEST80A01H501A', 'email' => 'rossi@example.test']);
        $this->pratica('ZZ-OK', 'ZZTEST80A01H501A');

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-OK'], '--render' => true])->assertExitCode(0);

        PdfModule::query()->update(['file_path' => 'module/non-esiste.pdf']);

        $this->artisan('modules:check-printable', ['--pratica' => ['ZZ-OK'], '--render' => true])
            ->expectsOutputToContain('compilazione fallita su 1 pratiche')
            ->assertExitCode(1);
    }
}

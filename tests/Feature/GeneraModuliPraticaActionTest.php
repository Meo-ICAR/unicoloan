<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Filament\Resources\Praticas\Pages\EditPratica;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class GeneraModuliPraticaActionTest extends TestCase
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

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    private function pratica(?string $codiceFiscale): Pratica
    {
        return Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-2026-001',
            'codice_fiscale' => $codiceFiscale,
            'tipo_prodotto' => 'Prestito',
            'amount' => 15000,
        ]);
    }

    private function module(string $name = 'Modulo prova', ?array $tipi = ['Prestito']): PdfModule
    {
        $path = 'module/'.Str::slug($name).'.pdf';
        Storage::disk('public')->put($path, $this->fixturePdfContents());

        $module = PdfModule::factory()->create(['name' => $name, 'file_path' => $path, 'tipi_prodotto' => $tipi]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente', 'source_key' => Key::ClienteNominativo]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'importo', 'source_key' => Key::ClienteIban]);

        return $module;
    }

    public function test_action_is_hidden_on_third_party_commitments(): void
    {
        $this->module();
        $pratica = $this->pratica('SCONOSCIUTO000000');
        $pratica->update(['is_notowned' => true]);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionHidden('generaModuli');
    }

    public function test_action_is_disabled_when_the_client_cannot_be_found(): void
    {
        $this->module();

        Livewire::test(EditPratica::class, ['record' => $this->pratica('SCONOSCIUTO000000')->getKey()])
            ->assertActionDisabled('generaModuli');
    }

    public function test_action_generates_the_selected_modules_and_notifies_with_download_links(): void
    {
        $this->skipUnlessPdftk();

        $code = strtoupper(Str::random(16));
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $module = $this->module();

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->assertActionEnabled('generaModuli')
            ->callAction('generaModuli', data: ['moduli' => [$module->id]])
            ->assertHasNoActionErrors()
            ->assertNotified('Modulo generato');

        $document = Document::where('documentable_id', $pratica->id)->sole();
        $fields = $this->readPdfFields(file_get_contents($document->getFirstMedia('documents')->getPath()));
        $this->assertSame('Rossi Mario', $fields['cliente']);

        $this->assertTrue(Activity::query()->where('event', 'moduli_generati')->sole()->subject->is($client));
    }

    public function test_inactive_modules_are_rejected_even_if_an_id_is_tampered(): void
    {
        $this->skipUnlessPdftk();

        $code = strtoupper(Str::random(16));
        Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $inactive = $this->module('Disattivo');
        $inactive->update(['is_active' => false]);
        $active = $this->module('Attivo');

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction('generaModuli', data: ['moduli' => [$inactive->id, $active->id]])
            ->assertHasActionErrors(['moduli.0']);

        $this->assertSame(0, Document::where('documentable_id', $pratica->id)->count());
    }

    public function test_a_missing_template_shows_an_error_and_creates_no_documents(): void
    {
        $code = strtoupper(Str::random(16));
        Client::create(['name' => 'Rossi', 'is_person' => true, 'tax_code' => $code]);
        $pratica = $this->pratica($code);
        $module = $this->module();
        $module->update(['file_path' => 'module/non-esiste.pdf']);

        Livewire::test(EditPratica::class, ['record' => $pratica->getKey()])
            ->callAction('generaModuli', data: ['moduli' => [$module->id]])
            ->assertNotified('Compilazione non disponibile');

        $this->assertSame(0, Document::where('documentable_id', $pratica->id)->count());
    }
}

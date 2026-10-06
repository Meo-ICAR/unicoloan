<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use App\Services\PdfFormException;
use App\Services\PraticaModuleGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PraticaModuleGeneratorTest extends TestCase
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

        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());
    }

    private function pratica(array $attributes = []): Pratica
    {
        return Pratica::create(array_merge([
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'P-2026-001',
            'amount' => 15000,
            'tipo_prodotto' => 'Prestito',
        ], $attributes));
    }

    private function client(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
            'iban' => 'IT60X0542811101000000123456',
        ], $attributes));
    }

    private function module(string $path = 'module/modulo-prova.pdf', string $name = 'Modulo prova'): PdfModule
    {
        $module = PdfModule::factory()->create(['name' => $name, 'file_path' => $path]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente', 'source_key' => Key::ClienteNominativo]);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'importo', 'source_key' => Key::PraticaImporto, 'formatter' => 'money_it']);
        PdfModuleField::factory()->checkbox('si')->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'consenso', 'source_key' => Key::ClientePersonaFisica]);

        return $module->load('fields');
    }

    public function test_generates_a_document_per_module_with_the_filled_pdf_attached(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();
        $module = $this->module();

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, [$module], $user);

        $this->assertCount(1, $documents);

        $document = $documents->first();
        $this->assertSame($pratica->id, $document->documentable_id);
        $this->assertSame($pratica::class, $document->documentable_type);
        $this->assertSame('Modulo prova - P-2026-001', $document->name);
        $this->assertSame('caricato', $document->status instanceof \BackedEnum ? $document->status->value : $document->status);
        $this->assertSame($user->id, $document->uploaded_by);

        $media = $document->getFirstMedia('documents');
        $this->assertNotNull($media);
        $this->assertSame('application/pdf', $media->mime_type);

        $fields = $this->readPdfFields(file_get_contents($media->getPath()));
        $this->assertSame('Rossi Mario', $fields['cliente']);
        $this->assertSame('15.000,00', $fields['importo']);
        $this->assertSame('si', $fields['consenso']);

        $this->assertTrue($pratica->documents()->whereKey($document->getKey())->exists());
    }

    public function test_logs_the_activity_on_the_client_without_sensitive_values(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, [$this->module()], $user);

        $activity = Activity::query()->where('event', 'moduli_generati')->sole();

        $this->assertSame('moduli_pdf', $activity->log_name);
        $this->assertTrue($activity->subject->is($client));
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame($pratica->id, $activity->getProperty('pratica_id'));
        $this->assertSame('P-2026-001', $activity->getProperty('codice_pratica'));
        $this->assertSame(['Modulo prova'], $activity->getProperty('moduli'));
        $this->assertSame([$documents->first()->getKey()], $activity->getProperty('documenti'));
        $this->assertStringNotContainsString('IT60X0542811101000000123456', json_encode($activity->properties));
    }

    public function test_a_failing_module_aborts_everything_without_leaving_partial_documents(): void
    {
        $good = $this->module();
        $broken = $this->module('module/non-esiste.pdf', 'Modulo rotto');

        try {
            app(PraticaModuleGenerator::class)->generate($this->pratica(), $this->client(), [$good, $broken], User::factory()->create());
            $this->fail('Doveva lanciare PdfFormException.');
        } catch (PdfFormException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Activity::query()->where('event', 'moduli_generati')->count());
    }

    public function test_generating_the_same_module_twice_keeps_both_documents(): void
    {
        $user = User::factory()->create();
        $pratica = $this->pratica();
        $client = $this->client();
        $module = $this->module();
        $generator = app(PraticaModuleGenerator::class);

        $generator->generate($pratica, $client, [$module], $user);
        $generator->generate($pratica, $client, [$module], $user);

        $this->assertSame(2, $pratica->documents()->count());
    }

    public function test_pratica_without_code_or_amount_still_generates_and_names_the_file_after_the_id(): void
    {
        $pratica = $this->pratica(['codice_pratica' => null, 'amount' => null]);

        $documents = app(PraticaModuleGenerator::class)->generate($pratica, $this->client(), [$this->module()], null);

        $document = $documents->sole();
        $this->assertSame('Modulo prova - '.$pratica->id, $document->name);
        $this->assertStringContainsString($pratica->id, $document->getFirstMedia('documents')->file_name);

        $fields = $this->readPdfFields(file_get_contents($document->getFirstMedia('documents')->getPath()));
        $this->assertNull($fields['importo']);
    }

    public function test_missing_by_module_lists_the_unavailable_source_keys(): void
    {
        $client = $this->client(['iban' => null]);
        $module = $this->module();
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'extra', 'source_key' => Key::ClienteIban]);
        $module->load('fields');

        $missing = app(PraticaModuleGenerator::class)->missingByModule($this->pratica(), $client, [$module]);

        $this->assertSame([Key::ClienteIban], $missing[$module->id]);
    }
}

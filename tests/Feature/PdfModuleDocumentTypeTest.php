<?php

namespace Tests\Feature;

use App\Filament\Resources\PdfModules\Pages\EditPdfModule;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use App\Services\PdfFieldSynchronizer;
use App\Services\PdfFormFiller;
use App\Services\PraticaModuleGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PdfModuleDocumentTypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function documentType(array $attributes = []): DocumentType
    {
        return DocumentType::query()->create(array_merge([
            'name' => 'Informativa privacy',
            'slug' => 'informativa-privacy',
            'nature' => 'template_fillable',
        ], $attributes));
    }

    public function test_module_and_document_type_are_linked_both_ways(): void
    {
        $type = $this->documentType();
        $module = PdfModule::factory()->create(['document_type_id' => $type->id]);

        $this->assertTrue($module->documentType->is($type));
        $this->assertTrue($type->pdfModule->is($module));
    }

    public function test_force_deleting_the_document_type_keeps_the_module_unlinked(): void
    {
        $type = $this->documentType();
        $module = PdfModule::factory()->create(['document_type_id' => $type->id]);

        $type->forceDelete();

        $this->assertNull($module->fresh()->document_type_id);
    }

    public function test_generated_documents_are_classified_with_the_module_document_type(): void
    {
        $this->mock(PdfFormFiller::class)->shouldReceive('fill')->andReturn('%PDF-1.4 finto');
        $type = $this->documentType();
        $linked = PdfModule::factory()->create(['document_type_id' => $type->id]);
        $unlinked = PdfModule::factory()->create();
        $pratica = Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'P-DT-1']);
        $client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16))]);

        $documents = app(PraticaModuleGenerator::class)
            ->generate($pratica, $client, [$linked, $unlinked], User::factory()->create());

        $this->assertSame($type->id, $documents[0]->document_type_id);
        $this->assertNull($documents[1]->document_type_id);
    }

    public function test_link_document_type_creates_a_fillable_template_type_once(): void
    {
        $module = PdfModule::factory()->create(['name' => 'QAV Persona fisica']);
        $synchronizer = app(PdfFieldSynchronizer::class);

        $type = $synchronizer->linkDocumentType($module);
        $again = $synchronizer->linkDocumentType($module->fresh());

        $this->assertTrue($type->is($again));
        $this->assertSame($type->id, $module->fresh()->document_type_id);
        $this->assertSame('template_fillable', $type->nature);
        $this->assertSame('modulo', $type->doctype);
        $this->assertTrue($type->is_template);
        $this->assertSame('qav-persona-fisica', $type->slug);
        $this->assertSame(1, DocumentType::where('slug', 'qav-persona-fisica')->count());
    }

    public function test_link_document_type_reuses_an_existing_type_with_the_same_slug(): void
    {
        $existing = $this->documentType(['name' => 'QAV Persona fisica', 'slug' => 'qav-persona-fisica', 'nature' => 'incoming']);
        $module = PdfModule::factory()->create(['name' => 'QAV Persona fisica']);

        $type = app(PdfFieldSynchronizer::class)->linkDocumentType($module);

        $this->assertTrue($type->is($existing));
        $this->assertSame($existing->id, $module->fresh()->document_type_id);
    }

    public function test_edit_form_links_a_document_type(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
        $type = $this->documentType();
        $module = PdfModule::factory()->create();

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->fillForm(['document_type_id' => $type->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($type->id, $module->fresh()->document_type_id);
    }
}

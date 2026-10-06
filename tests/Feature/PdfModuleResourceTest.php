<?php

namespace Tests\Feature;

use App\Enums\ModuleSourceKey;
use App\Enums\PdfModuleClientScope;
use App\Filament\Resources\PdfModules\Pages\EditPdfModule;
use App\Filament\Resources\PdfModules\Pages\ListPdfModules;
use App\Filament\Resources\PdfModules\PdfModuleResource;
use App\Filament\Resources\PdfModules\RelationManagers\FieldsRelationManager;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ReadsPdfFields;
use Tests\TestCase;

class PdfModuleResourceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsPdfFields;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    public function test_resource_is_registered_without_a_create_page(): void
    {
        $this->assertContains(PdfModuleResource::class, filament()->getPanel('admin')->getResources());
        $this->assertArrayNotHasKey('create', PdfModuleResource::getPages());
        $this->assertFalse(PdfModuleResource::canCreate());
    }

    public function test_list_shows_modules_with_their_field_counts(): void
    {
        $modules = PdfModule::factory()->count(2)->create();
        PdfModuleField::factory()->count(3)->create(['pdf_module_id' => $modules[0]->id]);

        Livewire::test(ListPdfModules::class)
            ->assertCanSeeTableRecords($modules)
            ->assertTableColumnStateSet('fields_count', 3, $modules[0]);
    }

    public function test_edit_form_updates_scope_products_and_active_flag(): void
    {
        $module = PdfModule::factory()->create();

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->fillForm([
                'name' => 'Fascicolo Prestiti',
                'client_scope' => PdfModuleClientScope::PersonaFisica->value,
                'tipi_prodotto' => ['Prestito', 'Microcredito'],
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $module->refresh();

        $this->assertSame('Fascicolo Prestiti', $module->name);
        $this->assertSame(PdfModuleClientScope::PersonaFisica, $module->client_scope);
        $this->assertSame(['Prestito', 'Microcredito'], $module->tipi_prodotto);
        $this->assertFalse($module->is_active);
    }

    public function test_fields_relation_manager_lists_fields_and_offers_the_whitelist_options(): void
    {
        $module = PdfModule::factory()->create();
        $fields = PdfModuleField::factory()->count(2)->create(['pdf_module_id' => $module->id]);

        $options = collect(ModuleSourceKey::cases())
            ->mapWithKeys(fn (ModuleSourceKey $case) => [$case->value => $case->getLabel()])
            ->all();

        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $module, 'pageClass' => EditPdfModule::class])
            ->assertCanSeeTableRecords($fields)
            ->assertTableSelectColumnHasOptions('source_key', $options, $fields->first());
    }

    public function test_fields_relation_manager_filters_unmapped_fields(): void
    {
        $module = PdfModule::factory()->create();
        $mapped = PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'source_key' => ModuleSourceKey::ClienteNome]);
        $unmapped = PdfModuleField::factory()->create(['pdf_module_id' => $module->id]);

        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $module, 'pageClass' => EditPdfModule::class])
            ->filterTable('non_mappati')
            ->assertCanSeeTableRecords([$unmapped])
            ->assertCanNotSeeTableRecords([$mapped]);
    }

    public function test_diagnostic_action_stores_the_pdf_and_notifies(): void
    {
        $this->skipUnlessPdftk();

        Storage::fake('public');
        Storage::disk('public')->put('module/modulo-prova.pdf', $this->fixturePdfContents());

        $module = PdfModule::factory()->create(['name' => 'Modulo prova', 'file_path' => 'module/modulo-prova.pdf']);
        PdfModuleField::factory()->create(['pdf_module_id' => $module->id, 'pdf_field_name' => 'cliente']);

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->callAction('diagnostica')
            ->assertNotified('PDF diagnostico pronto');

        Storage::disk('public')->assertExists('module-diagnostica/modulo-prova.pdf');
    }

    public function test_diagnostic_action_reports_an_error_when_the_pdf_is_missing(): void
    {
        Storage::fake('public');

        $module = PdfModule::factory()->create(['file_path' => 'module/non-esiste.pdf']);

        Livewire::test(EditPdfModule::class, ['record' => $module->getKey()])
            ->callAction('diagnostica')
            ->assertNotified('Compilazione non disponibile');
    }
}

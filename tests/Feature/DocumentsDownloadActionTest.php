<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Filament\RelationManagers\DocumentsRelationManager;

class DocumentsDownloadActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake(config('media-library.disk_name'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'tax_code' => strtoupper(Str::random(16))]);
    }

    private function document(array $type = [], bool $withModule = false, bool $withFile = false): Document
    {
        $type = $this->type(array_merge([
            'name' => 'Tipo',
            'slug' => 'tipo-'.Str::random(6),
            'nature' => 'incoming',
            'is_monitored' => false,
        ], $type));

        if ($withModule) {
            PdfModule::factory()->create(['document_type_id' => $type->id]);
        }

        $document = $this->client->documents()->create([
            'name' => 'Doc',
            'status' => 'caricato',
            'spatie_collection' => 'documents',
            'document_type_id' => $type->id,
        ]);

        if ($withFile) {
            $document->addMediaFromString('contenuto')->usingFileName('t.txt')->toMediaCollection('documents');
        }

        return $document->fresh();
    }

    /**
     * Crea il tipo documento; `is_client` / `is_practice` dicono a quale plico appartiene (task del cliente o della pratica).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function type(array $attributes): DocumentType
    {
        $type = DocumentType::query()->create($attributes);

        foreach (['is_client' => 'client', 'is_practice' => 'pratica'] as $flag => $taskable) {
            if (! empty($attributes[$flag])) {
                $task = Task::query()->firstOrCreate(['name' => 'Plico '.$taskable], ['taskable' => $taskable, 'is_active' => true]);
                $task->documentRequirements()->create(['document_type_id' => $type->getKey()]);
            }
        }

        return $type;
    }

    private function manager(): Testable
    {
        return Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->client, 'pageClass' => EditClient::class]);
    }

    public function test_pdf_module_print_is_offered_on_a_client_only_for_client_types(): void
    {
        $forClient = $this->document(['nature' => 'template_fillable', 'is_client' => true], withModule: true);

        $this->manager()->assertActionVisible(TestAction::make('downloadDocument')->table($forClient));
    }

    public function test_pdf_module_print_is_not_offered_on_a_client_for_practice_types(): void
    {
        $document = $this->document(['nature' => 'template_fillable', 'is_practice' => true], withModule: true);

        $this->manager()->assertActionHidden(TestAction::make('downloadDocument')->table($document));
    }

    public function test_new_document_offers_only_the_types_enabled_for_the_owner_model(): void
    {
        $forClient = $this->type(['name' => 'Per cliente', 'slug' => 'c-'.Str::random(6), 'nature' => 'incoming', 'is_client' => true]);
        $forPractice = $this->type(['name' => 'Per pratica', 'slug' => 'p-'.Str::random(6), 'nature' => 'incoming', 'is_practice' => true]);

        $this->manager()
            ->mountAction(TestAction::make('create')->table())
            ->assertSchemaComponentExists('document_type_id', checkComponentUsing: fn ($c) => array_key_exists($forClient->id, $c->getOptions()) && ! array_key_exists($forPractice->id, $c->getOptions()));
    }

    public function test_download_is_visible_for_a_downloadable_template_and_serves_the_file(): void
    {
        $document = $this->document(['is_template' => true], withFile: true);

        $this->manager()
            ->assertActionVisible(TestAction::make('downloadDocument')->table($document))
            ->callAction(TestAction::make('downloadDocument')->table($document))
            ->assertFileDownloaded('t.txt');
    }

    public function test_download_is_hidden_for_a_plain_received_document(): void
    {
        $document = $this->document(withFile: true);

        $this->manager()->assertActionHidden(TestAction::make('downloadDocument')->table($document));
    }

    public function test_download_is_hidden_for_an_already_signed_document(): void
    {
        $document = $this->document(['is_template' => true], withFile: true);
        $document->forceFill(['is_signed' => true])->save();

        $this->manager()->assertActionHidden(TestAction::make('downloadDocument')->table($document->fresh()));
    }

    public function test_type_filter_lists_pertinent_and_already_used_types_only(): void
    {
        $forClient = $this->type(['name' => 'Per cliente', 'slug' => 'c-'.Str::random(6), 'nature' => 'incoming', 'is_client' => true]);
        $forPractice = $this->type(['name' => 'Per pratica', 'slug' => 'p-'.Str::random(6), 'nature' => 'incoming', 'is_practice' => true]);
        $used = $this->document()->documentType;

        $options = (fn () => $this->documentTypeOptions(includeUsed: true))->call($this->manager()->instance());

        $this->assertArrayHasKey($forClient->id, $options);
        $this->assertArrayHasKey($used->id, $options);
        $this->assertArrayNotHasKey($forPractice->id, $options);
    }

    public function test_new_document_excludes_types_already_on_the_owner_but_keeps_the_edited_one(): void
    {
        $free = $this->type(['name' => 'Libero', 'slug' => 'l-'.Str::random(6), 'nature' => 'incoming', 'is_client' => true]);
        $document = $this->document(['is_client' => true]);
        $manager = $this->manager()->instance();

        $forNew = (fn () => $this->documentTypeOptions(excludeUsed: true))->call($manager);
        $forEdit = (fn () => $this->documentTypeOptions(currentId: $document->document_type_id, excludeUsed: true))->call($manager);

        $this->assertArrayHasKey($free->id, $forNew);
        $this->assertArrayNotHasKey($document->document_type_id, $forNew);
        $this->assertArrayHasKey($document->document_type_id, $forEdit);
    }
}

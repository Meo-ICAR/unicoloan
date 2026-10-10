<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentSchedules\Pages\ManageDocumentSchedules;
use App\Models\Client;
use App\Models\DocumentSchedule;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentReminderService;
use App\Support\DocumentRecipientResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Client $client;

    private DocumentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true, 'email' => 'mario@example.test']);
        $this->type = DocumentType::query()->create(['name' => 'Busta paga', 'slug' => 'bp-'.Str::random(5), 'nature' => 'incoming']);
    }

    private function document(string $status, array $attributes = [])
    {
        return $this->client->documents()->create(array_merge(['name' => 'Doc '.$status, 'status' => $status, 'spatie_collection' => 'documents', 'document_type_id' => $this->type->id], $attributes));
    }

    public function test_schedule_includes_missing_rejected_and_anomalous_documents_but_not_clean_or_approved_ones(): void
    {
        $missing = $this->document('richiesto');
        $rejected = $this->document('respinto', ['rejection_note' => 'Illeggibile']);
        $anomalous = $this->document('caricato', ['metadata' => ['verifica' => ['anomalie' => ['duplicato']]]]);
        $clean = $this->document('caricato', ['metadata' => ['verifica' => ['anomalie' => []]]]);
        $approved = $this->document('approvato');

        $ids = app(DocumentReminderService::class)->scheduleQuery()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$missing->id, $rejected->id, $anomalous->id], $ids);
        $this->assertNotContains($clean->id, $ids);
        $this->assertNotContains($approved->id, $ids);
    }

    public function test_sync_writes_the_rows_with_a_readable_entity_name_and_the_ui_lists_them_by_reason(): void
    {
        $missing = $this->document('richiesto');
        $rejected = $this->document('respinto', ['rejection_note' => 'Illeggibile']);

        Artisan::call('documents:sync-schedule');

        $rows = DocumentSchedule::query()->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Rossi Mario', $rows->first()->entity_name);

        Livewire::test(ManageDocumentSchedules::class)
            ->assertCanSeeTableRecords($rows)
            ->filterTable('reason', 'assente')
            ->assertCanSeeTableRecords($rows->where('document_id', $missing->id))
            ->assertCanNotSeeTableRecords($rows->where('document_id', $rejected->id))
            ->filterTable('reason', 'anomalia')
            ->assertCanSeeTableRecords($rows->where('document_id', $rejected->id))
            ->assertCanNotSeeTableRecords($rows->where('document_id', $missing->id));
    }

    public function test_documents_without_expiry_are_reminded_again_after_the_configured_days_and_keep_their_status(): void
    {
        config(['documents.missing_reminder_days' => 7]);
        $service = app(DocumentReminderService::class);
        $document = $this->document('richiesto');

        $this->assertTrue($service->shouldRemind($document));

        $document->forceFill(['last_sent_at' => now()->subDays(3)])->save();
        $this->assertFalse($service->shouldRemind($document->fresh()));

        $document->forceFill(['last_sent_at' => now()->subDays(8)])->save();
        $this->assertTrue($service->shouldRemind($document->fresh()));
    }

    public function test_recipient_of_a_client_document_is_the_client(): void
    {
        $document = $this->document('richiesto');

        $this->assertSame(['name' => 'Rossi Mario', 'email' => 'mario@example.test'], app(DocumentRecipientResolver::class)->resolveForDocument($document));
    }
}

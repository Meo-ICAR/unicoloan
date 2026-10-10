<?php

namespace Tests\Feature;

use App\Enums\DocumentAnomaly;
use App\Enums\SignatureRequestStatus;
use App\Filament\Resources\DocumentVerifications\Pages\ListDocumentVerifications;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\SignatureRequest;
use App\Models\SignatureRequestSigner;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake(config('media-library.disk_name'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->client = Client::create(['name' => 'Rossi', 'first_name' => 'Mario', 'is_person' => true]);
    }

    private function document(bool $needsSignature, string $status = 'richiesto', array $attributes = []): Document
    {
        $type = DocumentType::query()->create(['name' => 'Tipo '.Str::random(4), 'slug' => 'tipo-'.Str::random(6), 'nature' => 'incoming', 'is_signed' => $needsSignature, 'is_client' => true]);

        return $this->client->documents()->create(array_merge(['name' => 'Doc', 'status' => $status, 'spatie_collection' => 'documents', 'document_type_id' => $type->id], $attributes));
    }

    private function upload(Document $document, ?string $contents = null, string $collection = 'documents'): Document
    {
        $document->addMediaFromString($contents ?? file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')))->usingFileName('f.pdf')->toMediaCollection($collection);

        return $document->fresh();
    }

    public function test_upload_moves_a_requested_document_to_uploaded_and_stores_hash_and_checks(): void
    {
        $document = $this->upload($this->document(false));

        $this->assertSame('caricato', $document->status);
        $this->assertSame(64, strlen($document->file_hash));
        $this->assertSame('non_richiesta', $document->metadata['verifica']['signature']);
        $this->assertSame([], $document->metadata['verifica']['anomalie']);
    }

    public function test_signed_type_with_a_plain_scan_needs_the_operator_to_check_the_handwritten_signature(): void
    {
        $document = $this->upload($this->document(true));

        $this->assertSame('olografa', $document->metadata['verifica']['signature']);
        $this->assertNotContains('firma_mancante', $document->metadata['verifica']['anomalie']);
    }

    public function test_signed_type_with_a_digital_signature_marker_is_recognised(): void
    {
        $pdf = file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')).' /Type /Sig /ByteRange [0 10 20 30]';

        $this->assertSame('digitale', $this->upload($this->document(true), $pdf)->metadata['verifica']['signature']);
    }

    public function test_otp_signed_document_is_paired_with_the_yousign_transaction(): void
    {
        $document = $this->document(true, 'richiesto', ['is_signed' => true, 'signed_at' => now()]);
        $request = SignatureRequest::factory()->create(['document_id' => $document->getKey(), 'provider' => 'yousign', 'provider_ref' => 'sr_123', 'status' => SignatureRequestStatus::Signed, 'signed_at' => now()]);
        SignatureRequestSigner::query()->create(['signature_request_id' => $request->id, 'position' => 1, 'slot' => 'cliente', 'role' => 'client', 'name' => 'Mario Rossi', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'status' => 'signed', 'signed_at' => now()]);

        $verifica = $this->upload($document, null, 'signed')->metadata['verifica'];

        $this->assertSame('otp', $verifica['signature']);
        $this->assertSame([], $verifica['anomalie']);
        $this->assertSame(['yousign', 'sr_123', 'signed'], [$verifica['otp']['provider'], $verifica['otp']['provider_ref'], $verifica['otp']['status']]);
        $this->assertSame(['Mario Rossi', 'signed'], [$verifica['otp']['signers'][0]['name'], $verifica['otp']['signers'][0]['status']]);
    }

    public function test_open_transaction_is_flagged_and_a_signed_one_with_an_unsigned_signer_is_not_valid(): void
    {
        $pending = $this->document(true);
        SignatureRequest::factory()->create(['document_id' => $pending->getKey(), 'provider' => 'yousign', 'provider_ref' => 'sr_open', 'status' => SignatureRequestStatus::Sent]);

        $verifica = $this->upload($pending)->metadata['verifica'];
        $this->assertSame('otp_in_attesa', $verifica['signature']);
        $this->assertSame(['firma_mancante'], $verifica['anomalie']);

        $partial = $this->document(true, 'richiesto', ['is_signed' => true, 'signed_at' => now()]);
        $request = SignatureRequest::factory()->create(['document_id' => $partial->getKey(), 'provider' => 'yousign', 'provider_ref' => 'sr_partial', 'status' => SignatureRequestStatus::Signed]);
        SignatureRequestSigner::query()->create(['signature_request_id' => $request->id, 'position' => 1, 'slot' => 'cliente', 'role' => 'client', 'name' => 'Mario Rossi', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'status' => 'waiting']);

        $this->assertSame(['firma_non_valida'], $this->upload($partial, null, 'signed')->metadata['verifica']['anomalie']);
    }

    public function test_queue_shows_the_transaction_and_its_signers(): void
    {
        $document = $this->document(true, 'richiesto', ['is_signed' => true, 'signed_at' => now()]);
        $request = SignatureRequest::factory()->create(['document_id' => $document->getKey(), 'provider' => 'yousign', 'provider_ref' => 'sr_ok', 'status' => SignatureRequestStatus::Signed, 'signed_at' => now()]);
        SignatureRequestSigner::query()->create(['signature_request_id' => $request->id, 'position' => 1, 'slot' => 'cliente', 'role' => 'client', 'name' => 'Mario Rossi', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'status' => 'signed', 'signed_at' => now()]);
        $document = $this->upload($document, null, 'signed');

        Livewire::test(ListDocumentVerifications::class)
            ->assertCanSeeTableRecords([$document])
            ->assertSee('Yousign sr_ok')
            ->mountAction(TestAction::make('transazione')->table($document))
            ->assertActionMounted(TestAction::make('transazione')->table($document))
            ->assertHasNoErrors();
    }

    public function test_signed_type_without_a_readable_file_is_flagged_as_missing_signature_and_unreadable(): void
    {
        $document = $this->upload($this->document(true), 'questo non e un pdf');

        $this->assertEqualsCanonicalizing(['file_illeggibile', 'firma_mancante'], $document->metadata['verifica']['anomalie']);
    }

    public function test_expired_and_duplicate_documents_are_flagged(): void
    {
        $first = $this->document(false);
        $this->upload($first);
        $duplicate = $this->client->documents()->create(['name' => 'Doc2', 'status' => 'richiesto', 'spatie_collection' => 'documents', 'document_type_id' => $first->document_type_id, 'expires_at' => now()->subDay()->toDateString()]);

        $duplicate = $this->upload($duplicate);

        $this->assertEqualsCanonicalizing(['duplicato', 'scaduto'], $duplicate->metadata['verifica']['anomalie']);
    }

    public function test_queue_lists_only_uploaded_documents_and_the_operator_can_accept(): void
    {
        $toCheck = $this->upload($this->document(false));
        $approved = $this->upload($this->document(false));
        $approved->update(['status' => 'approvato']);
        $requested = $this->document(false);

        Livewire::test(ListDocumentVerifications::class)
            ->assertCanSeeTableRecords([$toCheck])
            ->assertCanNotSeeTableRecords([$approved, $requested])
            ->callAction(TestAction::make('accetta')->table($toCheck))
            ->assertNotified('Documento accettato');

        $toCheck->refresh();
        $this->assertSame('approvato', $toCheck->status);
        $this->assertSame(auth()->id(), (int) $toCheck->verified_by);
        $this->assertNotNull($toCheck->verified_at);
    }

    public function test_handwritten_signature_must_be_confirmed_before_accepting(): void
    {
        $document = $this->upload($this->document(true));

        Livewire::test(ListDocumentVerifications::class)
            ->callAction(TestAction::make('accetta')->table($document), [])
            ->assertHasFormErrors(['firma_verificata']);
        $this->assertSame('caricato', $document->fresh()->status);

        Livewire::test(ListDocumentVerifications::class)
            ->callAction(TestAction::make('accetta')->table($document), ['firma_verificata' => true])
            ->assertHasNoFormErrors();
        $this->assertSame('approvato', $document->fresh()->status);
    }

    public function test_reject_records_the_anomaly_and_the_note(): void
    {
        $document = $this->upload($this->document(false));

        Livewire::test(ListDocumentVerifications::class)
            ->callAction(TestAction::make('rifiuta')->table($document), ['anomalia' => DocumentAnomaly::NonConforming->value, 'nota' => 'Manca la seconda pagina'])
            ->assertNotified('Documento rifiutato');

        $document->refresh();
        $this->assertSame('respinto', $document->status);
        $this->assertSame('Documento non conforme: Manca la seconda pagina', $document->rejection_note);
        $this->assertSame('non_conforme', $document->metadata['verifica']['rifiuto']);
        $this->assertNotNull($document->verified_at);
    }

    public function test_reject_requires_an_anomaly(): void
    {
        $document = $this->upload($this->document(false));

        Livewire::test(ListDocumentVerifications::class)
            ->callAction(TestAction::make('rifiuta')->table($document), ['nota' => 'x'])
            ->assertHasFormErrors(['anomalia' => 'required']);
        $this->assertSame('caricato', $document->fresh()->status);
    }

    public function test_filter_keeps_only_documents_with_anomalies_or_a_signature_to_check(): void
    {
        $clean = $this->upload($this->document(false));
        $handwritten = $this->upload($this->document(true));

        Livewire::test(ListDocumentVerifications::class)
            ->filterTable('con_anomalie')
            ->assertCanSeeTableRecords([$handwritten])
            ->assertCanNotSeeTableRecords([$clean]);
    }
}

<?php

namespace Tests\Feature;

use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Enums\SignerRole;
use Unico\Core\Enums\SignerStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\SignatureRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function makeDocument(): Document
    {
        $client = Client::create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ]);

        return $client->documents()->create(['name' => 'QAV', 'status' => 'caricato', 'spatie_collection' => 'documents']);
    }

    private function addSigner(SignatureRequest $request, int $position, string $slot): void
    {
        $request->signers()->create([
            'position' => $position,
            'slot' => $slot,
            'role' => 'client',
            'name' => 'Mario Rossi',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);
    }

    public function test_signers_are_ordered_by_position_and_enums_are_cast(): void
    {
        $request = SignatureRequest::factory()->create(['document_id' => $this->makeDocument()->id]);
        $this->addSigner($request, 2, 'collaboratore');
        $this->addSigner($request, 1, 'cliente');

        $signers = $request->fresh()->signers;

        $this->assertSame(['cliente', 'collaboratore'], $signers->pluck('slot')->all());
        $this->assertSame(SignerRole::Client, $signers->first()->role);
        $this->assertSame(SignerStatus::Waiting, $signers->first()->status);
        $this->assertSame(SignatureRequestStatus::Pending, $request->fresh()->status);
        $this->assertTrue($request->document()->exists());
    }

    public function test_open_scope_includes_only_pending_and_sent(): void
    {
        $documentId = $this->makeDocument()->id;
        foreach (SignatureRequestStatus::cases() as $status) {
            SignatureRequest::factory()->create(['document_id' => $documentId, 'status' => $status]);
        }

        $open = SignatureRequest::open()->get();

        $this->assertEqualsCanonicalizing(
            [SignatureRequestStatus::Pending, SignatureRequestStatus::Sent],
            $open->pluck('status')->all(),
        );
        $this->assertTrue($open->every(fn (SignatureRequest $r) => $r->isOpen()));
        $this->assertFalse(SignatureRequest::factory()->make(['status' => SignatureRequestStatus::Signed])->isOpen());
    }

    public function test_latest_signature_request_is_the_one_with_highest_id(): void
    {
        $document = $this->makeDocument();
        $this->assertNull($document->latestSignatureRequest());

        SignatureRequest::factory()->create(['document_id' => $document->id]);
        $last = SignatureRequest::factory()->create(['document_id' => $document->id]);

        $this->assertSame($last->id, $document->latestSignatureRequest()->id);
        $this->assertCount(2, $document->signatureRequests);
    }

    public function test_pdf_module_persists_signature_slots_as_array(): void
    {
        $slots = [['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 10.5, 'y' => 20.0, 'width' => 100.0, 'height' => 30.0]];
        $module = PdfModule::create(['name' => 'QAV', 'file_path' => 'x.pdf', 'signature_slots' => $slots]);

        $this->assertEquals($slots, $module->fresh()->signature_slots);
        $this->assertNull(PdfModule::create(['name' => 'B', 'file_path' => 'b.pdf'])->fresh()->signature_slots);
    }

    public function test_signed_collection_is_single_file(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();

        $this->assertNotNull($document->getMediaCollection('signed'));
        $this->assertTrue($document->getMediaCollection('signed')->singleFile);

        $document->addMedia(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->toMediaCollection('signed');
        $document->addMedia(UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'))->toMediaCollection('signed');

        $this->assertCount(1, $document->fresh()->getMedia('signed'));
    }
}

<?php

namespace App\Services\Signature;

use App\Enums\SignatureRequestStatus;
use App\Enums\SignerStatus;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\Signature\Dto\EnvelopeData;
use App\Services\Signature\Dto\EnvelopeSigner;
use App\Services\Signature\Dto\SignaturePlacement;
use App\Services\Signature\Exceptions\SignatureException;
use App\Services\Signature\Exceptions\SignatureRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignatureRequestService
{
    public function __construct(private SignatureProviderManager $providers) {}

    /**
     * Invia il documento per la firma.
     *
     * @param  array<int, SignerInput>  $signers
     *
     * @throws SignatureRequestException
     */
    public function send(Document $document, array $signers, User $user): SignatureRequest
    {
        $media = $document->getFirstMedia('documents');

        if ($media === null || $media->mime_type !== 'application/pdf') {
            throw new SignatureRequestException('Il documento non ha un PDF da firmare.');
        }

        $slots = $document->documentType?->pdfModule?->signature_slots ?? [];

        if ($slots === []) {
            throw new SignatureRequestException('Il modulo non ha slot di firma configurati.');
        }

        $moduleSlots = array_column($slots, 'slot');
        $bySlot = [];
        foreach ($signers as $signer) {
            $bySlot[$signer->slot] = $signer;
        }

        $given = array_keys($bySlot);
        sort($given);
        $expected = $moduleSlots;
        sort($expected);

        if (count($signers) !== count($moduleSlots) || $given !== $expected) {
            throw new SignatureRequestException('I firmatari non corrispondono agli slot di firma del modulo.');
        }

        $this->assertNoOpenRequest($document);

        $provider = $this->providers->provider();
        $envelopeSigners = $this->buildEnvelopeSigners($slots, $bySlot, $provider->requiredContactFields());

        $pdf = (string) stream_get_contents($media->stream());

        $request = DB::connection('mysql')->transaction(function () use ($document, $envelopeSigners, $provider, $user): SignatureRequest {
            Document::query()->whereKey($document->getKey())->lockForUpdate()->first();
            $this->assertNoOpenRequest($document);

            $request = SignatureRequest::create([
                'document_id' => $document->getKey(),
                'provider' => $provider->name(),
                'status' => SignatureRequestStatus::Pending,
                'expires_at' => now()->addDays((int) config('signature.ttl_days')),
                'requested_by' => $user->getKey(),
            ]);

            foreach ($envelopeSigners as $signer) {
                $input = $signer['input'];
                $request->signers()->create([
                    'position' => $signer['envelope']->position,
                    'slot' => $input->slot,
                    'role' => $input->role,
                    'name' => trim($input->firstName.' '.$input->lastName),
                    'first_name' => $input->firstName,
                    'last_name' => $input->lastName,
                    'tax_code' => $input->taxCode,
                    'email' => $input->email,
                    'phone' => $signer['envelope']->phone,
                    'signer_type' => $input->signerType,
                    'signer_ref' => $input->signerRef,
                    'status' => SignerStatus::Waiting,
                ]);
            }

            return $request;
        });

        $envelope = new EnvelopeData(
            name: (string) $document->name,
            fileName: (Str::slug((string) $document->name) ?: 'documento').'.pdf',
            pdf: $pdf,
            signers: array_map(fn (array $signer): EnvelopeSigner => $signer['envelope'], $envelopeSigners),
            expiresAt: $request->expires_at,
            externalId: (string) $request->id,
        );

        try {
            $ref = $provider->createEnvelope($envelope);
        } catch (SignatureException $e) {
            $request->update([
                'status' => SignatureRequestStatus::Failed,
                'failure_reason' => $this->safeReason($e->getMessage()),
            ]);

            throw new SignatureRequestException('Invio per la firma non riuscito: '.$this->safeReason($e->getMessage()), 0, $e);
        }

        DB::connection('mysql')->transaction(function () use ($request, $ref, $user): void {
            $request->update([
                'provider_ref' => $ref->providerRef,
                'status' => SignatureRequestStatus::Sent,
                'sent_at' => now(),
            ]);

            foreach ($request->signers as $signer) {
                $signer->update([
                    'provider_signer_ref' => $ref->signerRefs[$signer->slot] ?? null,
                    'status' => SignerStatus::Notified,
                ]);
            }

            activity('firma')
                ->performedOn($request)
                ->causedBy($user)
                ->event('firma_inviata')
                ->withProperties(['signature_request_id' => $request->id, 'provider' => $request->provider])
                ->log('Documento inviato per la firma');
        });

        return $request->refresh();
    }

    private function assertNoOpenRequest(Document $document): void
    {
        if ($document->signatureRequests()->open()->exists()) {
            throw new SignatureRequestException('Esiste già una richiesta di firma aperta per questo documento.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     * @param  array<string, SignerInput>  $bySlot
     * @param  array<int, string>  $requiredFields
     * @return array<int, array{input: SignerInput, envelope: EnvelopeSigner}>
     */
    private function buildEnvelopeSigners(array $slots, array $bySlot, array $requiredFields): array
    {
        $result = [];

        foreach (array_values($slots) as $index => $slot) {
            $input = $bySlot[$slot['slot']];
            $label = trim($input->firstName.' '.$input->lastName) ?: $input->slot;

            if (trim($input->firstName) === '' || trim($input->lastName) === '') {
                throw new SignatureRequestException("Indicare nome e cognome del firmatario «{$input->slot}».");
            }

            if (in_array('email', $requiredFields, true) && blank($input->email)) {
                throw new SignatureRequestException("Email mancante per {$label}.");
            }

            $phone = null;
            if (in_array('phone', $requiredFields, true) && blank($input->phone)) {
                throw new SignatureRequestException("Telefono mancante per {$label}.");
            }
            if (filled($input->phone)) {
                $phone = PhoneNumber::toE164($input->phone);

                if ($phone === null) {
                    throw new SignatureRequestException("Telefono non valido per {$label}.");
                }
            }

            $result[] = [
                'input' => $input,
                'envelope' => new EnvelopeSigner(
                    $index + 1,
                    $input->slot,
                    $input->role,
                    $input->firstName,
                    $input->lastName,
                    filled($input->email) ? $input->email : null,
                    $phone,
                    $input->taxCode,
                    new SignaturePlacement((int) $slot['page'], (float) $slot['x'], (float) $slot['y'], (float) $slot['width'], (float) $slot['height']),
                ),
            ];
        }

        return $result;
    }

    /**
     * Motivo breve senza dati personali (email e sequenze numeriche lunghe oscurate).
     */
    private function safeReason(string $message): string
    {
        $message = preg_replace('/[^\s@]+@[^\s@]+/', '[email]', $message);
        $message = preg_replace('/\+?\d[\d\s\-().]{5,}\d/', '[numero]', (string) $message);

        return Str::limit(trim((string) $message), 200, '');
    }
}

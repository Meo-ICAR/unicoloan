<?php

namespace App\Services;

use App\Enums\DocumentAnomaly;
use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Enums\SignatureRequestStatus;
use Unico\Core\Enums\SignerStatus;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\SignatureRequestSigner;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Controlli automatici al caricamento di un documento. Il risultato e' in `metadata.verifica` e
 * serve all'operatore, che resta l'unico a poter accettare o rifiutare.
 */
class DocumentVerifier
{
    public const SIGNATURE_NOT_REQUIRED = 'non_richiesta';

    public const SIGNATURE_OTP = 'otp';

    public const SIGNATURE_DIGITAL = 'digitale';

    public const SIGNATURE_HANDWRITTEN = 'olografa';

    public const SIGNATURE_OTP_PENDING = 'otp_in_attesa';

    public const SIGNATURE_MISSING = 'assente';

    /**
     * @return array{signature: string, anomalie: array<int, string>, otp?: array<string, mixed>, checked_at: string}
     */
    public function check(Document $document): array
    {
        $media = $document->getFirstMedia('signed') ?? $document->getFirstMedia('documents');
        $anomalies = [];
        $contents = null;

        if ($media === null && blank($document->document_url)) {
            $anomalies[] = DocumentAnomaly::MissingFile->value;
        }

        if ($media !== null) {
            $contents = $this->readable($media);

            if ($contents === null) {
                $anomalies[] = DocumentAnomaly::Unreadable->value;
            } else {
                $hash = hash('sha256', $contents);
                $document->file_hash = $hash;

                if ($this->isDuplicate($document, $hash)) {
                    $anomalies[] = DocumentAnomaly::Duplicate->value;
                }
            }
        }

        if ($document->expires_at?->isPast()) {
            $anomalies[] = DocumentAnomaly::Expired->value;
        }

        $signature = $this->signature($document, $media, $contents);
        $otp = $this->transaction($document);

        if (in_array($signature, [self::SIGNATURE_MISSING, self::SIGNATURE_OTP_PENDING], true)) {
            $anomalies[] = DocumentAnomaly::SignatureMissing->value;
        }

        if ($signature === self::SIGNATURE_OTP && $otp !== null && collect($otp['signers'])->contains(fn (array $signer): bool => $signer['status'] !== SignerStatus::Signed->value)) {
            $anomalies[] = DocumentAnomaly::SignatureInvalid->value;
        }

        $result = array_filter(['signature' => $signature, 'anomalie' => $anomalies, 'otp' => $otp, 'checked_at' => now()->toIso8601String()], fn ($value) => $value !== null);

        $document->metadata = array_merge((array) $document->metadata, ['verifica' => $result]);

        if ($document->status === DocumentStatus::PENDING->value) {
            $document->status = DocumentStatus::UPLOADED->value;
        }

        $document->save();

        return $result;
    }

    /**
     * Esito della firma: otp / digitale (presente, non validata) / olografa (da verificare a vista) / assente / non richiesta.
     */
    private function signature(Document $document, ?Media $media, ?string $contents): string
    {
        if (! $document->documentType?->is_signed) {
            return self::SIGNATURE_NOT_REQUIRED;
        }

        $request = $this->latestRequest($document);

        if ($request?->status === SignatureRequestStatus::Signed || ($request === null && $document->is_signed && $document->signed_at !== null)) {
            return self::SIGNATURE_OTP;
        }

        if ($request?->isOpen()) {
            return self::SIGNATURE_OTP_PENDING;
        }

        if ($media === null || $contents === null) {
            return self::SIGNATURE_MISSING;
        }

        if (str_contains($contents, '/ByteRange') && str_contains($contents, '/Sig')) {
            return self::SIGNATURE_DIGITAL;
        }

        return self::SIGNATURE_HANDWRITTEN;
    }

    private function latestRequest(Document $document): ?SignatureRequest
    {
        return $document->signatureRequests()->with('signers')->latest('id')->first();
    }

    /**
     * La transazione di firma (Yousign) abbinata al documento: riferimento del provider, stato e firmatari.
     *
     * @return array{provider: string, provider_ref: ?string, status: string, signed_at: ?string, signers: array<int, array{name: string, role: string, status: string, signed_at: ?string}>}|null
     */
    private function transaction(Document $document): ?array
    {
        $request = $this->latestRequest($document);

        if ($request === null) {
            return null;
        }

        return [
            'provider' => $request->provider,
            'provider_ref' => $request->provider_ref,
            'status' => $request->status->value,
            'signed_at' => $request->signed_at?->toIso8601String(),
            'signers' => $request->signers->map(fn (SignatureRequestSigner $signer): array => [
                'name' => trim($signer->name ?: trim($signer->first_name.' '.$signer->last_name)),
                'role' => $signer->role->value,
                'status' => $signer->status->value,
                'signed_at' => $signer->signed_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Contenuto del file se esiste, non e' vuoto e (per i PDF) inizia con l'intestazione %PDF.
     */
    private function readable(Media $media): ?string
    {
        $path = $media->getPath();

        if (! is_file($path) || filesize($path) === 0) {
            return null;
        }

        $contents = (string) file_get_contents($path);

        $isPdf = $media->mime_type === 'application/pdf' || str_ends_with(strtolower($media->file_name), '.pdf');

        if ($isPdf && ! str_starts_with($contents, '%PDF')) {
            return null;
        }

        return $contents;
    }

    private function isDuplicate(Document $document, string $hash): bool
    {
        return Document::query()
            ->where('documentable_type', $document->documentable_type)
            ->where('documentable_id', $document->documentable_id)
            ->where('document_type_id', $document->document_type_id)
            ->where('file_hash', $hash)
            ->whereKeyNot($document->getKey())
            ->exists();
    }
}

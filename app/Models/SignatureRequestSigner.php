<?php

namespace App\Models;

use Unico\Core\Enums\SignerRole;
use Unico\Core\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\SignatureRequestSigner as CoreSignatureRequestSigner;

/**
 * Firmatario di una richiesta di firma.
 *
 * @property int $id
 * @property int $signature_request_id
 * @property SignerRole $role
 * @property SignerStatus $status
 */
class SignatureRequestSigner extends CoreSignatureRequestSigner
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'role' => SignerRole::class,
            'status' => SignerStatus::class,
            'signed_at' => 'datetime',
        ]);
    }

    public function signatureRequest(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class);
    }
}

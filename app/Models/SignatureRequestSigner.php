<?php

namespace App\Models;

use App\Enums\SignerRole;
use App\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Firmatario di una richiesta di firma.
 *
 * @property int $id
 * @property int $signature_request_id
 * @property SignerRole $role
 * @property SignerStatus $status
 */
class SignatureRequestSigner extends Model
{
    protected $connection = 'mysql';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'signature_request_id',
        'position',
        'slot',
        'role',
        'name',
        'first_name',
        'last_name',
        'tax_code',
        'email',
        'phone',
        'signer_type',
        'signer_ref',
        'status',
        'signed_at',
        'provider_signer_ref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SignerRole::class,
            'status' => SignerStatus::class,
            'signed_at' => 'datetime',
        ];
    }

    public function signatureRequest(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class);
    }
}

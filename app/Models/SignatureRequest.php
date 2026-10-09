<?php

namespace App\Models;

use App\Enums\SignatureRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Richiesta di firma di un Document presso un provider.
 *
 * @property int $id
 * @property string $document_id
 * @property SignatureRequestStatus $status
 */
class SignatureRequest extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'document_id',
        'provider',
        'provider_ref',
        'status',
        'sent_at',
        'signed_at',
        'expires_at',
        'last_synced_at',
        'last_event_at',
        'failure_reason',
        'requested_by',
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SignatureRequestStatus::class,
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signers(): HasMany
    {
        return $this->hasMany(SignatureRequestSigner::class)->orderBy('position');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [SignatureRequestStatus::Pending, SignatureRequestStatus::Sent]);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}

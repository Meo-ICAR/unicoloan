<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $connection = 'mysql';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('public');
    }

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'company_id',
        'documentable_type',
        'documentable_id',
        'document_type_id',
        'name',
        'docnumber',
        'spatie_collection',
        'document_url',
        'status',
        'sync_status',
        'source_app',
        'app_id',
        'app_drive_id',
        'app_etag',
        'extracted_text',
        'metadata',
        'ai_abstract',
        'ai_confidence_score',
        'is_template',
        'doctype',
        'cellposition',
        'is_signed',
        'is_unique',
        'is_endMonth',
        'is_monitored',
        'emitted_by',
        'emitted_at',
        'expires_at',
        'delivered_at',
        'signed_at',
        'description',
        'internal_notes',
        'rejection_note',
        'user_id',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'file_hash',
        'last_sent_at',
        'reminders_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_template' => 'boolean',
        'is_signed' => 'boolean',
        'is_unique' => 'boolean',
        'is_endMonth' => 'boolean',
        'is_monitored' => 'boolean',
        'emitted_at' => 'date',
        'expires_at' => 'date',
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
        'verified_at' => 'datetime',
        'ai_confidence_score' => 'integer',
        'last_sent_at' => 'datetime',
        'reminders_count' => 'integer',
    ];

    /**
     * Relazione: Tipo di documento
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);  // Presume l'esistenza del model DocumentType
    }

    /**
     * I "Booted" del Modello.
     * Intercetta le azioni del ciclo di vita di Eloquent.
     */
    protected static function booted(): void
    {
        static::saving(function (Document $document) {
            if (! empty($document->emitted_at)) {
                $document->expires_at = $document->documentType?->durationCalculate($document->emitted_at);
            }
            if (($document->status === DocumentStatus::PENDING) && ! empty($document->emitted_at)) {
                $document->status = DocumentStatus::APPROVED;
            }
            if ($document->status === DocumentStatus::REJECTED) {
                $document->rejection_note = $document->rejection_note ?? 'Nessuna nota fornita.';
            }
        });
    }

    /**
     * Relazione: Tenant proprietario
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione Polimorfica (es. User, Employee, Contract)
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Audit & User Relations ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(DocumentReminder::class);
    }

    public function renewedBy($document_id): string
    {
        $nomeDocumento = $this->name;
        $renewedById = $this->documentType()?->renewed_by_id;
        if ($renewedById) {
            $nomeDocumento = DocumentType::find($renewedById)->first()->name;
        }

        return $nomeDocumento;
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days)->toDateString());
    }

    // Dentro la classe Document...

    /**
     * Genera un nuovo aggiornamento/rinnovo per il documento corrente.
     *
     * @return Document Il nuovo documento creato
     */
    public function renew(): self
    {
        return DB::transaction(function () {
            // 1. Crea il nuovo documento ereditando i dati necessari
            $newDocument = self::create([
                'company_id' => $this->company_id,
                'documentable_type' => $this->documentable_type,
                'documentable_id' => $this->documentable_id,
                'document_type_id' => $this->document_type_id,
                'user_id' => $this->user_id,

                'name' => $this->name, // .' Agg. al '.now()->format('d/m/Y'),
                'doctype' => $this->doctype,
                'spatie_collection' => $this->spatie_collection,
                'description' => $this->description,
                'internal_notes' => $this->internal_notes,

                'status' => 'approved', // o DocumentStatus::APPROVED->value
                'is_monitored' => $this->is_monitored,
                'is_unique' => $this->is_unique,
                'is_endMonth' => $this->is_endMonth,
                'is_template' => false,

                'training_hours' => $this->training_hours,
                'training_organization' => $this->training_organization,

                'emitted_at' => now(),
                'created_by' => Auth::id(),
            ]);

            // 2. Aggiorna il record attuale (quello vecchio)
            $this->update([
                'status' => 'expired', // o DocumentStatus::EXPIRED->value
                'renewed_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'metadata' => array_merge($this->metadata ?? [], [
                    'renewed_to_uuid' => $newDocument->id,
                    'replaced_at' => now()->toIso8601String(),
                ]),
            ]);

            return $newDocument;
        });
    }
}

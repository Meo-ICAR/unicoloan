<?php

namespace App\Models;

use Unico\Core\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Unico\Core\Models\Document as CoreDocument;

class Document extends CoreDocument implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

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
        parent::booted();
        // Un documento eliminato (anche per sostituzione col firmato) esce subito dallo scadenziario.
        static::deleted(function (Document $document) {
            DocumentSchedule::query()->where('document_id', $document->getKey())->delete();
        });
    }

    /**
     * Relazione: Tenant proprietario
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    /**
     * Documento che ha sostituito questo (es. la versione firmata).
     */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function latestSignatureRequest(): ?SignatureRequest
    {
        return $this->signatureRequests()->latest('id')->first();
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
}

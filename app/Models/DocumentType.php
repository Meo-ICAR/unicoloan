<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Unico\Core\Models\DocumentType as CoreDocumentType;

class DocumentType extends CoreDocumentType implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $casts = [
        'is_company' => 'boolean',
        'is_signed' => 'boolean',
        'is_monitored' => 'boolean',
        'is_sensible' => 'boolean',
        'is_stored' => 'boolean',
        'is_end_month' => 'boolean',
        'is_AiAbstract' => 'boolean',
        'is_AiCheck' => 'boolean',
        'allow_auto_verification' => 'boolean',
        'notify_days_before' => 'array',
        'duration' => 'integer',
        'min_confidence' => 'integer',
        'retention_years' => 'integer',
    ];

    /**
     * Modulo PDF compilabile collegato (nature = template_fillable).
     */
    public function pdfModule(): HasOne
    {
        return $this->hasOne(PdfModule::class);
    }

    /**
     * Relazione con i documenti fisici caricati.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relazione Gerarchica: Il mio Responsabile diretto
     */
    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'renewed_by_id');
    }

    /**
     * I Task a cui è associato questo tipo di documento
     */
    public function tasks(): BelongsToMany
    {
        return $this
            ->belongsToMany(Task::class, 'document_requirements')
            ->withPivot('is_required')
            ->withTimestamps();
    }
}

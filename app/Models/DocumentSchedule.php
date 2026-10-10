<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Unico\Core\Models\DocumentSchedule as CoreDocumentSchedule;

class DocumentSchedule extends CoreDocumentSchedule
{
    protected $casts = [
        'expires_at' => 'date',
        'last_sent_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Relazione: Tipo di documento
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);  // Presume l'esistenza del model DocumentType
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo('documentable', 'documentable_type', 'documentable_id');
    }

    protected function entity(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->documentable_type || ! $this->documentable_id) {
                    return null;
                }

                // Recuperiamo la classe del Model dal morphMap
                $modelClass = Relation::getMorphedModel($this->documentable_type);

                return $modelClass ? $modelClass::find($this->documentable_id) : null;
            }
        );
    }
}

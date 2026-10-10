<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Documenti richiesti da un task: righe di `document_requirements` con `task_id` valorizzato. */
class TaskDocumentType extends Pivot
{
    use HasFactory;

    public function getConnectionName(): ?string
    {
        return config('unico-core.connection');
    }

    protected $table = 'document_requirements';

    protected $guarded = ['id'];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public $incrementing = true;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}

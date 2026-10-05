<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TaskDocumentType extends Pivot
{
    use HasFactory;

    /**
     * La tabella associata al modello.
     *
     * @var string
     */
    protected $connection = 'mysql';

    protected $table = 'task_document_types';

    /**
     * Gli attributi assegnabili in massa (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'document_type_id',
        'is_required',
    ];

    /**
     * Il casting degli attributi.
     * Converte automaticamente 0/1 del database in true/false in PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_required' => 'boolean',
    ];

    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (TaskDocumentType $pivot) {
            // Se lo slug è vuoto, lo andiamo a recuperare dal DocumentType collegato
            if (empty($pivot->slug) && $pivot->document_type_id) {
                $documentType = DocumentType::find($pivot->document_type_id);

                if ($documentType) {
                    $pivot->slug = $documentType->slug;
                }
            }
        });
    }

    /**
     * Relazione: ottiene il Task a cui è associato questo record.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relazione: ottiene la Tipologia di Documento associata a questo record.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}

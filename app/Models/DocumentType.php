<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DocumentType extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'name',
        'description',
        'document_url',
        'code',
        'codegroup',
        'slug',
        'regex_pattern',
        'priority',
        'phase',
        'is_person',
        'is_company',
        'is_employee',
        'is_agent',
        'is_principal',
        'is_client',
        'is_practice',
        'is_signed',
        'is_monitored',
        'doctype',
        'cellposition',
        'renewed_by_id',
        'duration',
        'duration_unit',
        'emitted_by',
        'is_sensible',
        'is_template',
        'is_stored',
        'regex',
        'is_endMonth',
        'document_typable',
        'is_AiAbstract',
        'is_AiCheck',
        'AiPattern',
        'min_confidence',
        'allow_auto_verification',
        'notify_days_before',
        'retention_years',
        'created_by',
        'updated_by',
        'deleted_by',
        'trigger_field', 'trigger_state', 'trigger_value', 'exclude_field', 'exclude_state',
        'exclude_value',

    ];

    protected $casts = [
        'is_person' => 'boolean',
        'is_company' => 'boolean',
        'is_employee' => 'boolean',
        'is_agent' => 'boolean',
        'is_principal' => 'boolean',
        'is_client' => 'boolean',
        'is_practice' => 'boolean',
        'is_signed' => 'boolean',
        'is_monitored' => 'boolean',
        'is_sensible' => 'boolean',
        'is_template' => 'boolean',
        'is_stored' => 'boolean',
        'is_endMonth' => 'boolean',
        'is_AiAbstract' => 'boolean',
        'is_AiCheck' => 'boolean',
        'allow_auto_verification' => 'boolean',
        'notify_days_before' => 'array',
        'priority' => 'integer',
        'duration' => 'integer',
        'min_confidence' => 'integer',
        'retention_years' => 'integer',
    ];

    public function durationCalculate(Carbon $emittedAt): ?Carbon
    {
        // Calcola la data di scadenza
        if (! $emittedAt) {
            return null;
        }
        if (! $this->is_monitored) {
            return null;
        }
        $expirationDate = $emittedAt->copy();
        switch ($this->duration_unit) {
            case 'days':
                $expirationDate = $expirationDate->addDays($this->duration);
                break;
            case 'months':
                $expirationDate = $expirationDate->addMonths($this->duration);
                break;
            case 'years':
                $expirationDate = $expirationDate->addYears($this->duration);
                break;
            default:
                $expirationDate = null;
                break;
        }
        if ($this->is_endMonth && $expirationDate) {
            $expirationDate = $expirationDate->endOfMonth();
        }

        return $expirationDate;
    }

    /**
     * Relazione con i documenti fisici caricati.
     */
    public function documents()
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
            ->belongsToMany(Task::class, 'task_document_types')
            ->withPivot('is_required')
            ->withTimestamps();
    }
}

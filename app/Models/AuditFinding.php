<?php

namespace App\Models;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Concerns\LogsComplianceActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditFinding extends Model
{
    use HasFactory, LogsComplianceActivity, SoftDeletes;

    /**
     * Il nome della tabella associata al modello.
     * Specificato esplicitamente per evitare ambiguità con la migrazione.
     *
     * @var string
     */
    protected $connection = 'mysql';

    protected $table = 'audit_findings';

    /**
     * I campi che possono essere assegnati massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'audit_id',
        'company_id',
        'title',
        'description',
        'severity',
        'requires_investigation',
        'investigation_notes',
        'investigation_deadline',
        'requires_corrective_action',
        'corrective_action_description',
        'remediation_id',
        'corrective_action_deadline',
        'status',
        'resolved_at',
        'resolution_notes',
    ];

    /**
     * I cast nativi per gli attributi del database.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requires_investigation' => 'boolean',
        'requires_corrective_action' => 'boolean',
        'investigation_deadline' => 'date',
        'corrective_action_deadline' => 'date',
        'resolved_at' => 'date',
        // Mappatura automatica sui PHP Enums per Filament
        'severity' => FindingSeverity::class,
        'status' => FindingStatus::class,
    ];

    /**
     * Relazione con l'Audit principale a cui questo rilievo appartiene.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Relazione con l'azienda (Tenant / Proprietaria del dato).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * SCOPE: Filtra solo i rilievi attualmente aperti o in lavorazione.
     * Utile in Filament per creare tab di filtro rapido o widget contatori.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [FindingStatus::Open, FindingStatus::InProgress]);
    }

    /**
     * SCOPE: Filtra i rilievi che hanno superato la data limite di risoluzione.
     */
    public function scopeOverdue($query)
    {
        return $query
            ->whereIn('status', [FindingStatus::Open, FindingStatus::InProgress])
            ->where(function ($q) {
                $q
                    ->where('corrective_action_deadline', '<', now())
                    ->orWhere('investigation_deadline', '<', now());
            });
    }

    /**
     * HELPER: Verifica se il rilievo è scaduto rispetto alle scadenze impostate.
     * Può essere usato per formattare il testo o lo sfondo in Filament.
     */
    public function isOverdue(): bool
    {
        if (in_array($this->status, [FindingStatus::Resolved, FindingStatus::Closed, FindingStatus::AcceptedRisk])) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->requires_investigation && $this->investigation_deadline?->isBefore($today)) {
            return true;
        }

        if ($this->requires_corrective_action && $this->corrective_action_deadline?->isBefore($today)) {
            return true;
        }

        return false;
    }
}

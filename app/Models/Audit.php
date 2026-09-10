<?php

namespace App\Models;

use App\Enums\AuditStatus;
use App\Models\Concerns\LogsComplianceActivity;
use App\ValueObjects\OamSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Audit extends Model
{
    use HasFactory, LogsComplianceActivity, SoftDeletes;

    protected $connection = 'mysql';

    /**
     * Gli attributi assegnabili in massa (Mass Assignable).
     * In Laravel 13 si preferisce la notazione dei tipi in formato list.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'auditable_type',
        'auditable_id',
        'auditor_name',
        'organization_id',
        'requested_by_user_id',
        'scheduled_at',
        'executed_at',
        'status',
        'protocol_number',
        'origin_type',
        'execution_method',
        'scope',
        'outcome',
        'summary',
        'auditor_notes',
        'remediation_plan',
        'followup_date',
        'severity',
        'rilievi_codes',
        'collaboratore',
    ];

    /**
     * Definizione dei Casts (Nuovo standard nativo da Laravel 11/12/13 tramite metodo).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date',
            'executed_at' => 'date',
            'followup_date' => 'date',
            // Cast nativo verso il PHP Enum (Filament lo adora)
            'status' => AuditStatus::class,
            'rilievi_codes' => 'array', // o 'json'
        ];
    }

    /**
     * I "Booted" del Modello.
     * Intercetta le azioni del ciclo di vita di Eloquent.
     */
    protected static function booted(): void
    {
        static::creating(function (Audit $audit) {
            // Se non è già stato specificato un company_id, assegna la prima Company presente
            if (blank($audit->company_id)) {
                $audit->company_id = Company::first()?->id;
            }

            if (blank($audit->protocol_number)) {
                $audit->protocol_number = static::nextProtocolNumber();
            }
        });
    }

    /**
     * -----------------------------------------------------------------
     * RELAZIONI ELOQUENT
     * -----------------------------------------------------------------
     */

    /**
     * Relazione col Tenant (La Company principale del mediatore creditizio)
     * Fondamentale se usi il Multi-tenancy nativo di Filament 5.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Numero di protocollo progressivo per anno, formato "AAAA/NNNN".
     * L'indice unique su protocol_number intercetta eventuali collisioni.
     */
    public static function nextProtocolNumber(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $lastSeq = (int) static::withTrashed()
            ->where('protocol_number', 'like', $year.'/%')
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(protocol_number, "/", -1) AS UNSIGNED)) as seq')
            ->value('seq');

        return sprintf('%d/%04d', $year, $lastSeq + 1);
    }

    /**
     * Il Soggetto Controllato (Polimorfismo).
     * Può restituire un modello Impiegato (Employee), Produttore/Agente (Agent), ecc.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'Organismo di Vigilanza esterno che ha richiesto o imposto l'audit (es. OAM, IVASS).
     */
    public function organization(): BelongsTo
    {
        // Se hai chiamato il modello "Organization", usa Organization::class
        // Se lo hai chiamato "SupervisoryBody", usa SupervisoryBody::class
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * L'utente interno che ha richiesto l'apertura dell'audit (es. Responsabile Compliance).
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopePerSemestreOam(Builder $query, OamSemester $semester): Builder
    {
        return $query->where('executed_at', '<=', $semester->end)
            ->where('executed_at', '>=', $semester->start);

    }

    public function scopeRilieviOam(Builder $query, OamSemester $semester): Builder
    {
        return $query->where('executed_at', '<=', $semester->end)
            ->where('executed_at', '>=', $semester->start)
            ->whereNotNull('outcome')
            ->where('outcome', '!=', 'conforme');

    }

    /**
     * Calcola il numero di audit previsti (funzione Compliance).
     */
    public static function auditEffettuatiPerPeriodo($inizio, $fine): int
    {
        return (int) static::query()
         //   ->where('funzione', 'compliance')
            ->where('execution_method', 'audit')
            ->whereNotNull('executed_at')
            ->where('executed_at', '<=', $fine)
            ->where('executed_at', '>=', $inizio)
            ->count();
    }

    /**
     * Calcola il numero di ispezioni previsti (funzione Compliance).
     */
    public static function ispezioniEffettuatiPerPeriodo($inizio, $fine): int
    {
        return (int) static::query()
          //  ->where('funzione', 'compliance')
            ->where('execution_method', 'ispezione')
            ->whereNotNull('executed_at')
            ->where('executed_at', '<=', $fine)
            ->where('executed_at', '>=', $inizio)
            ->count();
    }
}

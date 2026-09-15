<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintMacroCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ReceptionChannel;
use App\Models\Concerns\LogsComplianceActivity;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplaintRegistry extends Model
{
    use HasFactory, LogsComplianceActivity, SoftDeletes;

    // Specifica il nome corretto della tabella se diverso dal plurale standard di Laravel
    protected $connection = 'mysql';

    protected $table = 'complaint_registry';

    protected $fillable = [
        'company_id',
        'protocol_number',
        'received_at',
        'reception_channel',
        'receiving_email',
        'complainant_type',
        'complainant_id',
        'complainant_name',
        'complainant_email',
        'macro_category',
        'category',
        'subject_type',
        'subject_id',
        'agent_id',
        'bank_id',
        'description',
        'financial_impact',
        'status',
        'deadline_at',
        'is_extended',
        'resolved_at',
        'resolution_notes',
        'escalated_to',
    ];

    /**
     * Cast degli attributi per l'integrazione nativa con gli Enum.
     */
    protected $casts = [
        'received_at' => 'date',
        'deadline_at' => 'date',
        'resolved_at' => 'datetime',
        'financial_impact' => 'decimal:2',
        'is_extended' => 'boolean',
        // Colleghiamo gli Enum per fare in modo che Filament legga i badge e i colori
        'status' => ComplaintStatus::class,
        'macro_category' => ComplaintMacroCategory::class,
        'category' => ComplaintCategory::class,
        'reception_channel' => ReceptionChannel::class,
    ];

    /**
     * I "Booted" del Modello.
     * Intercetta le azioni del ciclo di vita di Eloquent.
     */
    protected static function booted(): void
    {
        static::creating(function (ComplaintRegistry $complaint) {
            // Se non è già stato specificato un company_id, assegna la prima Company presente
            if (blank($complaint->company_id)) {
                $complaint->company_id = Company::first()?->id;
            }

            if (blank($complaint->protocol_number)) {
                $complaint->protocol_number = static::nextProtocolNumber();
            }
        });
    }

    /**
     * Numero di protocollo progressivo per anno, formato "AAAA/NNNN".
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

    // ==========================================
    // RELAZIONI POLIMORFICHE (I MORPHS)
    // ==========================================

    /** Chi ha fatto il reclamo (es. Cliente, Dipendente, OAM, Fornitore) */

    /**
     * Ottiene il reclamante (può essere un Fornitore, un Cliente, ecc.)
     */
    public function complainant(): MorphTo
    {
        return $this->morphTo('complainant');
    }

    /**
     * L'oggetto del reclamo (es. una specifica Pratica, un Lead, un Contratto)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ==========================================
    // RELAZIONI DIRETTE (TIPICHE DEL MEDIATORE)
    // ==========================================

    /**
     * L'agente o collaboratore della rete commerciale coinvolto nel reclamo.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'agent_id');
    }

    /**
     * La Banca Mandante o l'Istituto Erogante coinvolto nel prodotto contestato.
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Clienti::class, 'bank_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // ==========================================
    // LOGICA DI BUSINESS / HELPER UTILI
    // ==========================================

    /**
     * Verifica se il reclamo ha superato la data massima di risoluzione senza essere chiuso.
     */
    public function isOverdue(): bool
    {
        if (in_array($this->status, [ComplaintStatus::Accepted, ComplaintStatus::Rejected])) {
            return false;
        }

        return $this->deadline_at && $this->deadline_at->isPast();
    }
}

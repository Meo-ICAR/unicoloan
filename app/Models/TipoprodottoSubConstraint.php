<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoprodottoSubConstraint extends Model
{
    // protected $connection = 'mysql_proforma';

    protected $table = 'tipoprodotto_sub_constraints';

    /**
     * Disabilitiamo i timestamps nativi di Laravel (created_at/updated_at)
     * poiché non sono presenti nello schema SQL fornito.
     */
    public $timestamps = false;

    /**
     * I campi assegnabili massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipoprodotto_id',
        'tipoprodotto_sub_id',
        'clienti_id',
        'role_id',
        'min_age',
        'max_age_at_maturity',
        'min_amount',
        'max_amount',
        'min_duration_months',
        'max_duration_months',
        'min_employment_months',
        'max_debt_to_income_ratio',
        'max_ltv_percentage',
        'allowed_employment_types',
        'additional_rules_json',
        'additional_notes',
        'is_active',
    ];

    /**
     * Cast dei tipi di dato di Eloquent.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tipoprodotto_id' => 'integer',
        'tipoprodotto_sub_id' => 'integer',
        'fornitorirole_id' => 'integer',
        'min_age' => 'integer',
        'max_age_at_maturity' => 'integer',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'min_duration_months' => 'integer',
        'max_duration_months' => 'integer',
        'min_employment_months' => 'integer',
        'max_debt_to_income_ratio' => 'decimal:2',
        'max_ltv_percentage' => 'decimal:2',

        // Cast automatico da stringa JSON a array PHP per una manipolazione immediata
        'allowed_employment_types' => 'array',
        'additional_rules_json' => 'array',
        'is_active' => 'boolean',

    ];

    /**
     * Relazione con il Prodotto principale (tipoprodotto).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Tipoprodotto::class, 'tipoprodotto_id');
    }

    /**
     * Relazione con il Sottoprodotto (tipoprodotto_sub).
     */
    public function subproduct(): BelongsTo
    {
        return $this->belongsTo(TipoprodottoSub::class, 'tipoprodotto_sub_id');
    }

    /**
     * Relazione con la Banca / Istituto erogante (clienti).
     * Nota: Nella migrazione punta a 'clientis' (plurale con la s).
     */
    public function client(): BelongsTo
    {
        // Adatta il nome della classe del modello (es. Cliente o Clientis) in base al tuo progetto
        return $this->belongsTo(Cliente::class, 'clienti_id');
    }

    /**
     * Relazione con il ruolo/livello del fornitore associato a questo vincolo.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(FornitoriRole::class, 'fornitorirole_id');
    }
}

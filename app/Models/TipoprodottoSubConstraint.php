<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\TipoProdottoSubConstraint as CoreTipoProdottoSubConstraint;

class TipoprodottoSubConstraint extends CoreTipoProdottoSubConstraint
{
    /**
     * Disabilitiamo i timestamps nativi di Laravel (created_at/updated_at)
     * poiché non sono presenti nello schema SQL fornito.
     */
    public $timestamps = false;

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
        return $this->belongsTo(Clienti::class, 'clienti_id');
    }

    /**
     * Relazione con il ruolo/livello del fornitore associato a questo vincolo.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(FornitoriRole::class, 'fornitorirole_id');
    }
}

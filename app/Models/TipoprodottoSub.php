<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\TipoProdottoSub as CoreTipoProdottoSub;

class TipoprodottoSub extends CoreTipoProdottoSub
{
    /**
     * I cast nativi per i tipi di dato.
     *
     * @var array<string, string>
     */
    protected $casts = [

        'is_active' => 'boolean',
    ];

    /**
     * Ottiene il prodotto finanziario principale a cui appartiene questo sottoprodotto.
     * Relazione Molti a 1.
     */
    public function tipoProdotto(): BelongsTo
    {
        return $this->belongsTo(Tipoprodotto::class, 'tipoprodotto_id');
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function provvigioni(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(ProvvigioniRule::class, 'tipoprodotto_sub_id');
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function limits(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(TipoprodottoSubConstraint::class, 'tipoprodotto_sub_id');
    }

    /**
     * Regole di configurazione dei requisiti per questo sottotipo prodotto.
     */
    public function regoleRequisiti(): HasMany
    {
        return $this->hasMany(RequisitoTipoFinanziamento::class, 'tipoprodotto_sub_id');
    }

    /**
     * Requisiti direttamente associati a questo sottotipo di prodotto.
     */
    public function requisiti(): BelongsToMany
    {
        return $this->belongsToMany(
            PraticaRequisito::class,
            'document_requirements',
            'tipoprodotto_sub_id',
            'document_type_id'
        )->withPivot(['is_required', 'sort_order'])
            ->orderBy('document_requirements.sort_order');
    }
}

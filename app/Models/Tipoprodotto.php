<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\TipoProdotto as CoreTipoProdotto;

class Tipoprodotto extends CoreTipoProdotto
{
    /**
     * I cast nativi per i tipi di dato.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_external' => 'boolean',
        'is_oneclient' => 'boolean',
        'is_active' => 'boolean',
        'for_person' => 'boolean',
        'for_company' => 'boolean',
        'requires_mandate' => 'boolean',
        'is_third_party' => 'boolean',
    ];

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function subproducts(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(TipoprodottoSub::class, 'tipoprodotto_id');
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function provvigioni(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(ProvvigioniRule::class, 'tipoprodotto_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tipoprodotto extends Model
{
    protected $connection = 'mysql_proforma';

    protected $table = 'proforma.tipoprodotto';

    /**
     * I campi che possono essere assegnati massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_external',
        'is_oneclient',
        'is_active',
        'oam',
        'tipo_provvigioni',
    ];

    /**
     * I cast nativi per i tipi di dato.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_external' => 'boolean',
        'is_oneclient' => 'boolean',
        'is_active' => 'boolean',
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

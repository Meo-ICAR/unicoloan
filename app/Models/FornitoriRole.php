<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FornitoriRole extends Model
{
    protected $connection = 'mysql_proforma';

    protected $table = 'proforma.fornitoriroles';

    /**
     * I campi assegnabili massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'level',
        'description',
    ];

    /**
     * Cast dei tipi di dato.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
    ];

    /**
     * Ottiene i vincoli sui prodotti associati a questo specifico ruolo dei fornitori.
     * Relazione 1 a Molti.
     */
    public function productConstraints(): HasMany
    {
        return $this->hasMany(TipoprodottoSubConstraint::class, 'role_id');
    }

    /**
     * Ottiene i sottoprodotti associati a questo prodotto finanziario.
     * Relazione 1 a Molti.
     */
    public function provvigioni(): HasMany
    {
        // Specifichiamo la chiave esterna poiché il modello non si chiama 'TipoprodottoSub' standard
        return $this->hasMany(ProvvigioniRule::class, 'kind_id');
    }
}

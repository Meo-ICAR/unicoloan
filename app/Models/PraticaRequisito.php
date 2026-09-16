<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PraticaRequisito extends Model
{
    /**
     * Connessione esplicita: senza di essa, quando questo modello viene caricato
     * tramite una relazione da un modello PROFORMA (connessione mysql_proforma,
     * es. Pratica), erediterebbe erroneamente quella connessione invece di
     * usare 'mysql', dove vive realmente questa tabella.
     */
    protected $connection = 'mysql';

    protected $table = 'pratica_requisiti';

    protected $fillable = [
        'codice',
        'name',
        'descrizione',
    ];

    /**
     * Configurazione del requisito legato ai vari tipi di prodotto.
     */
    public function regoleProdotto(): HasMany
    {
        return $this->hasMany(RequisitoTipoFinanziamento::class, 'pratica_requisito_id');
    }

    /**
     * Istanze operative create per le singole pratiche.
     */
    public function operativi(): HasMany
    {
        return $this->hasMany(PraticaRequisitoOperativo::class, 'pratica_requisito_id');
    }
}

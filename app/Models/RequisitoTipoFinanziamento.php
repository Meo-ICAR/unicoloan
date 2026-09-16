<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoTipoFinanziamento extends Model
{
    /**
     * Connessione esplicita: senza di essa, quando questo modello viene caricato
     * tramite una relazione da un modello PROFORMA (connessione mysql_proforma,
     * es. TipoprodottoSub), erediterebbe erroneamente quella connessione invece
     * di usare 'mysql', dove vive realmente questa tabella.
     */
    protected $connection = 'mysql';

    protected $table = 'requisito_tipo_finanziamento';

    public $timestamps = false; // Tabella pivot personalizzata senza timestamps di default

    protected $fillable = [
        'tipoprodotto_id',
        'tipoprodotto_sub_id',
        'pratica_requisito_id',
        'obbligatorio',
        'ordine',
    ];

    protected $casts = [
        'obbligatorio' => 'boolean',
        'ordine' => 'integer',
    ];

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(PraticaRequisito::class, 'pratica_requisito_id');
    }

    public function subTipoProdotto(): BelongsTo
    {
        return $this->belongsTo(TipoprodottoSub::class, 'tipoprodotto_sub_id');
    }
}

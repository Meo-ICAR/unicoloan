<?php

namespace App\Models\PROFORMA;

use App\ValueObjects\OamSemester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provvigione extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $connection = 'mysql_proforma';

    protected $table = 'provvigioni';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'data_inserimento_compenso',
        'descrizione',
        'tipo',
        'importo',
        'importo_effettivo',
        'erogated_at',
        'importo_erogato',
        'status_compenso',
        'data_pagamento',
        'n_fattura',
        'data_fattura',
        'data_status',
        'denominazione_riferimento',
        'entrata_uscita',
        'id_pratica',
        'segnalatore',
        'istituto_finanziario',
        'piva',
        'cf',
        'annullato',
        'coordinamento',
        'iscliente',
        'stato',
        'proforma_id',
        'legacy_id',
        'invoice_number',
        'cognome',
        'quota',
        'nome',
        'fonte',
        'tipo_pratica',
        'data_inserimento_pratica',
        'data_stipula',
        'prodotto',
        'macrostatus',
        'status_pratica',
        'status_pagamento',
        'data_status_pratica',
        'montante',
        'importo_erogato',
        'sended_at',
        'received_at',
        'paided_at',
        'upload_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */

    /**
     * Get the pratica that owns the provvigione.
     */
    public function pratica()
    {
        return $this->belongsTo(Pratica::class, 'id_pratica', 'id');
    }

    protected $casts = [
        'importo' => 'decimal:2',
        'importo_effettivo' => 'decimal:2',
        'erogated_at' => 'datetime',
        'importo_erogato' => 'decimal:2',
        'montante' => 'decimal:2',
        'annullato' => 'boolean',
        'coordinamento' => 'boolean',
        'data_inserimento_compenso' => 'date',
        'data_pagamento' => 'date',
        'data_fattura' => 'date',
        'data_status' => 'date',
        'data_inserimento_pratica' => 'date',
        'data_stipula' => 'date',
        'data_status_pratica' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sended_at' => 'datetime',
        'received_at' => 'datetime',
        'paided_at' => 'datetime',
        'upload_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'annullato' => false,
    ];

    public static function getProvvigioneCliente(string $id_pratica): ?float
    {
        $provvcliente = static::where('id_pratica', $id_pratica)
            ->where('tipo', '=', 'Cliente')
            ->sum('importo');

        return $provvcliente ? $provvcliente : 0.0;
    }

    public static function getPremioIstituto(string $id_pratica, string $istituto): ?float
    {
        $provvcliente = static::where('id_pratica', $id_pratica)
            ->where('tipo', '=', 'Istituto')
            //     ->where('denominazione_riferimento', '=', $istituto)
            ->where('descrizione', 'like', '%premio%')
            ->sum('importo');

        return $provvcliente ? $provvcliente : 0.0;
    }

    public static function getProvvigioneIstituto(string $id_pratica, string $istituto): float
    {
        // sum() restituisce 0 se non trova nulla, quindi basta forzare il cast a float
        return (float) static::where('id_pratica', $id_pratica)
            ->where('tipo', 'Istituto')
            // ->where('denominazione_riferimento', $istituto)
            ->whereNot('descrizione', 'like', '%premio%')
            ->sum('importo');
    }

    // FIX: cambiato il parametro da $tipo a $id_pratica e aggiunto il $semester
    public static function getProvvigioneStorno(string $id_pratica, ?OamSemester $semester = null): float
    {
        // Fallback: se non passi il semestre, prendiamo quello corrente
        $semester = $semester ?? OamSemester::getInBaseAlMeseCorrente();

        $totale = (float) static::where('id_pratica', $id_pratica)
            ->where('tipo', 'Istituto')
            ->perSemestreOam($semester) // Ora lo scope riceve l'oggetto corretto
            ->where('descrizione', 'like', '%storno%')
            ->sum('importo');

        // Se è 0 ritorna 0.0, altrimenti ritorna l'importo negativo
        return $totale === 0.0 ? 0.0 : $totale;
    }

    public static function getProvvigioneAgenti(string $id_pratica): ?float
    {
        $provvcliente = static::where('id_pratica', $id_pratica)
            ->where('tipo', '=', 'Agente')
            ->sum('importo');

        return $provvcliente ? (float) $provvcliente : 0.0;
    }

    /**
     * Provvigioni con data_status compresa nel semestre indicato.
     */
    public function scopePerSemestreOam(Builder $query, ?OamSemester $semester = null): Builder
    {
        $semester ??= OamSemester::current();

        return $query
            ->where('data_status', '>=', $semester->start)
            ->where('data_status', '<=', $semester->end);
    }

    /**
     * Storni di provvigione Istituto contabilizzati nel semestre indicato.
     */
    public function scopeStorniOam(Builder $query, ?OamSemester $semester = null): Builder
    {
        $semester ??= OamSemester::current();

        return $query
            ->where('tipo', 'Istituto')
            ->where('descrizione', 'like', '%storno%')
            ->where('data_status', '>=', $semester->start)
            ->where('data_status', '<=', $semester->end);
    }
}

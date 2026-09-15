<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvvigioniRule extends Model
{
    use HasFactory;

    /**
     * La connessione al database associata al modello.
     * Deve essere esplicita, altrimenti le relazioni hasMany/hasOne definite
     * su modelli PROFORMA (connessione mysql_proforma) erediterebbero
     * erroneamente quella connessione invece di usare 'mysql'.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * Il nome della tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'provvigioni_rules';

    /**
     * Gli attributi assegnabili in massa (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipoprodotto_id',
        'tipoprodotto_sub_id',
        'clienti_id',
        'kind_id',
        'fornitori_id',
        'coordinamento',
        'iscliente',
        'tipo_provvigioni',
        'value',
        'valid_from',
        'valid_to',
        'notes',
    ];

    /**
     * Il casting degli attributi per Laravel 11/12/13.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordinamento' => 'boolean', // Mappa tinyint(1) a booleano
            'iscliente' => 'boolean', // Mappa tinyint(1) a booleano
            'value' => 'decimal:4', // Mantiene la precisione di 4 decimali
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relazioni
    |--------------------------------------------------------------------------
    */

    public function tipoprodotto(): BelongsTo
    {
        return $this->belongsTo(Tipoprodotto::class, 'tipoprodotto_id');
    }

    public function tipoprodottoSub(): BelongsTo
    {
        return $this->belongsTo(TipoprodottoSub::class, 'tipoprodotto_sub_id');
    }

    /**
     * L'istituto di credito che impone il vincolo.
     */
    public function clienti(): BelongsTo
    {
        return $this->belongsTo(Clienti::class, 'clienti_id');
    }

    /**
     * L'agente o fornitore di riferimento.
     */
    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitori_id');
    }

    /**
     * Ruolo/Livello dell'agente (es. Senior, Junior).
     */
    public function fornitoriRole(): BelongsTo
    {
        return $this->belongsTo(FornitoriRole::class, 'kind_id');
    }
}

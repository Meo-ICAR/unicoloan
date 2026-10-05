<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PraticaStato extends Model
{
    use SoftDeletes;

    protected $table = 'pratica_stati';

    protected $fillable = [
        'codice',
        'name',
        'ordine',
        'is_rejected',
        'is_working',
        'is_estingued',
        'colore',
        'icona',
    ];

    protected $casts = [
        'ordine' => 'integer',
        'is_rejected' => 'boolean',
        'is_working' => 'boolean',
        'is_estingued' => 'boolean',
    ];

    /**
     * Stati di destinazione raggiungibili da questo stato.
     */
    public function transizioniSuccessive(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'pratica_stati_transizioni',
            'stato_da_id',
            'stato_a_id'
        );
    }

    /**
     * Stati di provenienza che possono portare a questo stato.
     */
    public function transizioniPrecedenti(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'pratica_stati_transizioni',
            'stato_a_id',
            'stato_da_id'
        );
    }

    /**
     * Pratiche attualmente in questo stato.
     */
    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'stato_id');
    }
}

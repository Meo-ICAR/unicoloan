<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PraticaStati extends Model
{
    /**
     * Il nome della tabella.
     */
    protected $table = 'proforma.pratiches_statos';

    /**
     * La chiave primaria della tabella.
     */
    protected $primaryKey = 'stato_pratica';

    /**
     * Indica se la chiave primaria è auto-incrementante (nel nostro caso no).
     */
    public $incrementing = false;

    /**
     * Il tipo di dato della chiave primaria.
     */
    protected $keyType = 'string';

    /**
     * Disabilita i timestamps (created_at / updated_at) poiché assenti nel DB.
     */
    public $timestamps = false;

    protected $fillable = [
        'stato_pratica',
        'isrejected',
        'isworking',
        'isestingued',
    ];

    /**
     * Cast degli attributi.
     */
    protected function casts(): array
    {
        return [
            // Convertiamo gli int in booleani per comodità di utilizzo
            'isrejected' => 'boolean',
            'isworking' => 'boolean',
            'isestingued' => 'boolean',
        ];
    }
}

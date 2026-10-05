<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anagrafica degli stati possibili di un compenso/provvigione (App\Models\PROFORMA\Provvigione.status_compenso).
 * Vive sulla connessione mysql_proforma, tabella legacy del gestionale PROFORMA.
 *
 * @property string $status_compenso Stato del compenso (chiave primaria)
 * @property bool $isperfezionato Indica se questo stato corrisponde a una pratica perfezionata/erogata
 */
class Compenso extends Model
{
    protected $connection = 'mysql_proforma';

    protected $table = 'compensos';

    protected $primaryKey = 'status_compenso';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'status_compenso',
        'isperfezionato',
    ];

    protected $casts = [
        'isperfezionato' => 'boolean',
    ];
}

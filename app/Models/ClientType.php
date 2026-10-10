<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Unico\Core\Models\ClientType as CoreClientType;

/**
 * @property int $id ID univoco tipo cliente
 * @property string $name Descrizione
 * @property bool $is_person Persona fisica (true) o giuridica (false)
 * @property bool $is_company Indica se è una società/azienda
 * @property string|null $privacy_role Ruolo Privacy (es. Titolare Autonomo, Responsabile Esterno)
 * @property string|null $purpose Finalità del trattamento
 * @property string|null $data_subjects Categorie di Interessati
 * @property string|null $data_categories Categorie di Dati Trattati
 * @property string|null $retention_period Tempi di Conservazione (Data Retention)
 * @property string|null $extra_eu_transfer Trasferimento Extra-UE
 * @property string|null $security_measures Misure di Sicurezza
 * @property string|null $privacy_data Altri Dati Privacy
 * @property Carbon $created_at Data di creazione
 * @property Carbon $updated_at Ultima modifica
 */
class ClientType extends CoreClientType
{
    use HasFactory;

    /**
     * Gli attributi che devono essere convertiti in tipi nativi (Casting).
     * Converte i minuscoli tinyint(1) del DB in veri booleani PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_person' => 'boolean',
        'is_company' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

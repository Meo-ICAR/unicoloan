<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
class ClientType extends Model
{
    use HasFactory;

    /**
     * Il nome della tabella associata al modello.
     * Laravel lo indovinerebbe da solo (client_types), ma definirlo esplicitamente è una buona pratica.
     *
     * @var string
     */
    protected $table = 'client_types';

    /**
     * Gli attributi che sono assegnabili in modo massivo (Mass Assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'is_person',
        'is_company',
        'privacy_role',
        'purpose',
        'data_subjects',
        'data_categories',
        'retention_period',
        'extra_eu_transfer',
        'security_measures',
        'privacy_data',
    ];

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

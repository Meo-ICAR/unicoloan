<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientMandate extends Model
{
    use SoftDeletes;

    /**
     * I campi che possono essere assegnati massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'numero_mandato',
        'data_firma_mandato',
        'data_scadenza_mandato',
        'importo_richiesto_mandato',
        'scopo_finanziamento',
        'data_consegna_trasparenza',
        'stato',
        'ruolo',
        'name',
        'notes',
        'purpose_of_relationship',
        'funds_origin',
        'oam_delivered',
        'role_risk_level',
    ];

    /**
     * Il cast degli attributi ai tipi nativi di PHP/Carbon.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Booleani da tinyint
        'oam_delivered' => 'boolean',

        // Decimali
        'importo_richiesto_mandato' => 'decimal:2',

        // Date (Cast a oggetti Date/Carbon senza ore)
        'data_firma_mandato' => 'date',
        'data_scadenza_mandato' => 'date',
        'data_consegna_trasparenza' => 'date',

        // Timestamp completi
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =========================================================================
    // RELAZIONI
    // =========================================================================

    /**
     * Il cliente intestatario/coinvolto in questo mandato finanziario.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relazione polimorfica con i documenti (se i mandati possiedono allegati fisici firmati).
     * Mappa documentable_type = 'App\Models\ClientMandate'
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

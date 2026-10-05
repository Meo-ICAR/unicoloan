<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;  // <-- Usa Pivot invece di Model

// use Illuminate\Database\Eloquent\Model;

class ClientiOam extends Pivot
{
    use HasFactory;

    // Forza il nome della tabella visto che non segue la pluralizzazione inglese standard

    protected $connection = 'mysql';

    protected $table = 'clienti_oam';

    protected $fillable = [
        'clienti_id',
        'oam_code_id',
        'dal',
        'al',
    ];

    // Cast corretti per le date in modo che Filament le gestisca come oggetti Carbon
    protected $casts = [
        'dal' => 'date',
        'al' => 'date',
    ];

    /**
     * Intercetta gli eventi del database per questo modello.
     */
    protected static function booted(): void
    {
        // L'evento 'creating' scatta PRIMA che la riga venga inserita nel DB (nuova spunta)
        static::creating(function (ClientiOam $pivotRecord) {
            // Se il campo 'dal' non è stato passato dal form, forziamo il default
            if (empty($pivotRecord->dal)) {
                $pivotRecord->dal = '2026-01-01';
            }
        });

        // Se vuoi intercettare anche gli aggiornamenti (es. se modifichi la relazione dopo)

        // static::updating(function (ClientiOam $pivotRecord) {
        // Logica di update qui se necessaria
        // });
    }

    /**
     * Relazione con la Mandataria (Cliente)
     */
    public function cliente(): BelongsTo
    {
        // Se il modello si chiama 'Cliente', cambialo qui di conseguenza
        return $this->belongsTo(Clienti::class, 'clienti_id');
    }

    /**
     * Relazione con il Codice OAM
     */
    public function oamCode(): BelongsTo
    {
        return $this->belongsTo(OamCode::class, 'oam_code_id');
    }
}

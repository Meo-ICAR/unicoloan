<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\ProvvigioniRule as CoreProvvigioniRule;

class ProvvigioniRule extends CoreProvvigioniRule
{
    use HasFactory;

    /**
     * Il casting degli attributi per Laravel 11/12/13.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'coordinamento' => 'boolean', // Mappa tinyint(1) a booleano
            'iscliente' => 'boolean', // Mappa tinyint(1) a booleano
            'value' => 'decimal:4', // Mantiene la precisione di 4 decimali
            'valid_from' => 'date',
            'valid_to' => 'date',
        ]);
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

<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\BlacklistClienteFornitore as CoreBlacklistClienteFornitore;

class BlacklistClienteFornitore extends CoreBlacklistClienteFornitore
{
    use HasFactory;

    /**
     * Il casting degli attributi (sintassi Laravel 11/12/13).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'data_inizio' => 'date',
            'data_fine' => 'date',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relazioni
    |--------------------------------------------------------------------------
    */

    /**
     * L'istituto di credito che ha imposto il blocco.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Clienti::class, 'cliente_id');
    }

    /**
     * L'agente (fornitore) bloccato.
     */
    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitore_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scope Locali
    |--------------------------------------------------------------------------
    */

    /**
     * Scope per filtrare solo i blocchi attualmente attivi
     * (data fine nulla oppure successiva a oggi).
     */
    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('data_fine')
                ->orWhere('data_fine', '>=', now());
        });
    }
}

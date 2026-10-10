<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\BlacklistClienteEmployee as CoreBlacklistClienteEmployee;

class BlacklistClienteEmployee extends CoreBlacklistClienteEmployee
{
    use HasFactory;

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
     * Il dipendente bloccato.
     *
     * `employees` vive su una connessione/database diversa da `blacklist_clienti_employees`
     * (rispettivamente `mysql` e `mysql_proforma`): belongsTo esegue una query separata
     * sulla connessione del modello correlato, quindi funziona correttamente anche
     * senza una foreign key a livello di database.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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

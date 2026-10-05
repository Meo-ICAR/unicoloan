<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlacklistClienteEmployee extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql_proforma';

    protected $table = 'blacklist_clienti_employees';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Gli attributi assegnabili in massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cliente_id',
        'employee_id',
        'motivo',
        'data_inizio',
        'data_fine',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio' => 'date',
            'data_fine' => 'date',
        ];
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

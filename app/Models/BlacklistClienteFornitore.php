<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlacklistClienteFornitore extends Model
{
    use HasFactory;

    protected $connection = 'mysql_proforma';

    /**
     * Il nome della tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'blacklist_clienti_fornitori';

    /**
     * Gli attributi assegnabili in massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cliente_id',
        'fornitore_id',
        'motivo',
        'data_inizio',
        'data_fine',
    ];

    /**
     * Il casting degli attributi (sintassi Laravel 11/12/13).
     *
     * @return array<string, string>
     */
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

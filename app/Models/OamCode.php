<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti; // Assicurati di importarlo
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OamCode extends Model
{
    use HasFactory;

    // Definito esplicitamente per mappare la tabella plurale corretta
    protected $connection = 'mysql';

    protected $table = 'oam_codes';

    protected $fillable = [
        'code',
        'name',
        'description',
        'tipo_prodotto',
        'is_dummy',
        'is_active',
        // 'submission_type',
    ];

    public function clienti(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Clienti::class,
                'clienti_oam',
                'oam_code_id',  // Invertito: prima la chiave di questo modello nella pivot
                'clienti_id'  // Poi la chiave del modello correlato
            )
            ->where('is_active', true)  // <-- FILTRO: Mostra solo Client attivi
            ->withPivot('dal', 'al')
        //    ->withPivot('dal', 'al', 'submission_type')
            ->withTimestamps();
    }
}

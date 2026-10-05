<?php

namespace App\Models;

use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Spatie\Activitylog\Traits\LogsActivity;

class PraticaRequisitoOperativo extends Model
{
    // use LogsActivity;

    /**
     * Connessione esplicita: senza di essa, quando questo modello viene caricato
     * tramite una relazione da un modello PROFORMA (connessione mysql_proforma,
     * es. Pratica), erediterebbe erroneamente quella connessione invece di
     * usare 'mysql', dove vive realmente questa tabella.
     */
    protected $connection = 'mysql';

    protected $table = 'pratica_requisiti_operativi';

    protected $fillable = [
        'pratica_id',
        'pratica_requisito_id',
        'stato',
        'data_richiesta',
        'data_completamento',
        'note',
    ];

    protected $casts = [
        'data_richiesta' => 'datetime',
        'data_completamento' => 'datetime',
    ];

    // --- RELAZIONI ---

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(PraticaRequisito::class, 'pratica_requisito_id');
    }

    // --- SCOPES DI RICERCA ---

    public function scopeObbligatori(Builder $query): Builder
    {
        return $query->where('is_obbligatorio', true);
    }

    public function scopeAperti(Builder $query): Builder
    {
        return $query->where('stato', '!=', 'approvato');
    }

    public function scopeCompletati(Builder $query): Builder
    {
        return $query->where('stato', 'approvato');
    }

    // --- HELPER METHOD PER AZIONI RAPIDE ---

    /**
     * Segna il requisito come richiesto (es. inoltrata richiesta polizza)
     */
    public function segnaComeRichiesto(?string $note = null): void
    {
        $this->update([
            'stato' => 'richiesto',
            'data_richiesta' => now(),
            'note' => $note ?? $this->note,
        ]);
    }

    /**
     * Segna il requisito come completato/approvato (es. polizza emessa)
     */
    public function segnaComeApprovato(?string $note = null): void
    {
        $this->update([
            'stato' => 'approvato',
            'data_completamento' => now(),
            'note' => $note ?? $this->note,
        ]);
    }
}

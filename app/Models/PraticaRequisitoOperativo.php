<?php

namespace App\Models;

use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\DocumentRequest as CoreDocumentRequest;

/**
 * Requisito operativo di una pratica Proforma. Nel pacchetto è una richiesta documento (`document_requests`)
 * con `requestable_type = 'pratica'` e `requestable_id` = id della pratica (testo, perché letta da Proforma).
 * Colonne: `stato` → `status`, `data_richiesta` → `requested_at`, `data_completamento` → `completed_at`, `note` → `notes`.
 */
class PraticaRequisitoOperativo extends CoreDocumentRequest
{
    // --- RELAZIONI ---

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'requestable_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(PraticaRequisito::class, 'document_type_id');
    }

    // --- SCOPES DI RICERCA ---

    public function scopeObbligatori(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeAperti(Builder $query): Builder
    {
        return $query->where('status', '!=', 'approvato');
    }

    public function scopeCompletati(Builder $query): Builder
    {
        return $query->where('status', 'approvato');
    }

    // --- HELPER METHOD PER AZIONI RAPIDE ---

    public function segnaComeRichiesto(?string $note = null): void
    {
        $this->update([
            'status' => 'richiesto',
            'requested_at' => now(),
            'notes' => $note ?? $this->notes,
        ]);
    }

    public function segnaComeApprovato(?string $note = null): void
    {
        $this->update([
            'status' => 'approvato',
            'completed_at' => now(),
            'notes' => $note ?? $this->notes,
        ]);
    }
}

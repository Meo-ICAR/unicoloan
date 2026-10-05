<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PraticaDocumentRequest extends Model
{
    protected $connection = 'mysql_proforma';

    protected $table = 'proforma.pratica_document_requests';

    public $timestamps = false; // se gestiti manualmente o se hai creato la tabella senza timestamps

    protected $fillable = [
        'pratica_id',
        'name',
        'description',
        'requested_by',      // <-- Nuovo campo
        'assigned_to_type',  // <-- Nuovo campo
        'status',
        'share_with_client', // <-- Nuovo campo
        'due_date',
        'file_path',
        'rejected_reason',
        'agent_notified_at', // <-- Nuovo campo
        'client_notified_at', // <-- Nuovo campo
    ];

    protected $casts = [
        'due_date' => 'date',
        'share_with_client' => 'boolean',
        'agent_notified_at' => 'datetime',
        'client_notified_at' => 'datetime',
    ];

    public function integrationRequestedByBank(): bool
    {
        return $this->requested_by === 'clienti';
    }

    public function isAssignedToClient(): bool
    {
        return $this->assigned_to_type === 'clienti';
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }
}

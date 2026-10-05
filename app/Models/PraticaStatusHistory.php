<?php

// app/Models/PraticaStatusHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PraticaStatusHistory extends Model
{
    protected $connection = 'mysql_proforma';

    protected $table = 'proforma.pratica_status_history';

    protected $fillable = [
        'pratica_id',
        'status_from',
        'status_to',
        'changed_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }
}

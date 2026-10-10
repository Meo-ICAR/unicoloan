<?php

// app/Models/PraticaStatusHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\PraticaStatusHistory as CorePraticaStatusHistory;

class PraticaStatusHistory extends CorePraticaStatusHistory
{
    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class, 'pratica_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\DocumentReminder as CoreDocumentReminder;

class DocumentReminder extends CoreDocumentReminder
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ]);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

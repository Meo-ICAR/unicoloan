<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Unico\Core\Models\Website as CoreWebsite;

class Website extends CoreWebsite
{
    use HasFactory, SoftDeletes;
    protected $orderBy = 'name';
    protected $orderDirection = 'asc';

    /**
     * Relazione diretta con la Company proprietaria
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione diretta con la Company proprietaria
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Clienti::class, 'clienti_id');
    }
}

<?php

namespace App\Models;

use App\Models\PROFORMA\Clienti;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';
    protected $orderBy = 'name';
    protected $orderDirection = 'asc';

    /**
     * I campi assegnabili in massa.
     */
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'clienti_id',
        'is_active',
        'domain',
        'is_typical',
        'privacy_date',
        'transparency_date',
        'privacy_prior_date',
        'transparency_prior_date',
        'url_privacy',
        'url_cookies',
        'is_footercompilant',
        'url_transparency',
        'is_iso27001_certified',
        'websiteable_type',
        'websiteable_id',
    ];

    /**
     * I cast nativi di Laravel per gestire correttamente booleani e date.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_typical' => 'boolean',
        'is_footercompilant' => 'boolean',
        'is_iso27001_certified' => 'boolean',
        'privacy_date' => 'date',
        'transparency_date' => 'date',
        'privacy_prior_date' => 'date',
        'transparency_prior_date' => 'date',
    ];

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

    /**
     * Relazione Polimorfica che punta al modello collegato tramite UUID
     */
    public function websiteable(): MorphTo
    {
        return $this->morphTo();
    }
}

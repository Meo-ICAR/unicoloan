<?php

namespace App\Models;

use App\Models\PROFORMA\Fornitore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'branches';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    /**
     * I campi assegnabili in massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'address',
        'street_number',
        'city',
        'zip_code',
        'province',
        'region',
        'branchable_type',
        'branchable_id',
        'is_main_office',
        'manager_first_name',
        'manager_last_name',
        'manager_tax_code',
        'founded_at',
        'dismissed_at',
        'is_active',
    ];

    /**
     * I cast dei campi.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_main_office' => 'boolean',
        'is_active' => 'boolean',
        'founded_at' => 'date',
        'dismissed_at' => 'date',
    ];

    /**
     * Relazione con la Company principale (Tenant)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione Polimorfica.
     * Consente di ottenere il modello proprietario della filiale (es. Hotel, Call Center, ecc.)
     */
    public function branchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function employee()
    {
        return $this->HasMany(Employee::class);
    }

    public function fornitore()
    {
        return $this->HasMany(Fornitore::class);
    }
}

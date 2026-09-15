<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'role_title',
        'cf',
        'email',
        'pec',
        'phone',
        'department',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'oam_dismissed_at',
        'ivass',
        'hiring_date',
        'termination_date',
        'branch_id',
        'coordinated_by_id',
        'employee_type',

        'supervisor_type',
        'privacy_role',
        'purpose',
        'data_subjects',
        'data_categories',
        'retention_period',
        'extra_eu_transfer',
        'security_measures',
        'privacy_data',
        'is_structure',
        'is_ghost',
        'employee_roles',
        'is_external',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_structure' => 'boolean',
        'is_ghost' => 'boolean',
        'is_external' => 'boolean',
        'oam_at' => 'date',
        'oam_dismissed_at' => 'date',
        'hiring_date' => 'date',
        'termination_date' => 'date',
        'employee_roles' => 'array', // Converte automaticamente JSON <-> Array
    ];

    /**
     * Relazione: Tenant Azienda
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione: Account di Login
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relazione: Filiale/Sede assegnata
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relazione Gerarchica: Il mio Responsabile diretto
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coordinated_by_id');
    }

    /**
     * Relazione Gerarchica: Le persone che coordino (il mio Team)
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'coordinated_by_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Le banche che hanno bloccato questo dipendente (blacklist).
     */
    public function bancheBlacklist(): HasMany
    {
        return $this->hasMany(BlacklistClienteEmployee::class, 'employee_id');
    }

    /**
     * Helper per verificare rapidamente se il dipendente è bloccato da una specifica banca.
     */
    public function isBlacklistedBy(string $clienteId): bool
    {
        return $this->bancheBlacklist()
            ->attivi()
            ->where('cliente_id', $clienteId)
            ->exists();
    }

    /**
     * Scope generico per filtrare per qualsiasi tipologia di ruolo (JSON).
     */
    public function scopeHasRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('employee_roles', $role);
    }

    public function scopeAuditors(Builder $query): Builder
    {
        return $query->whereJsonContains('employee_roles', 'audit');
    }

    public function scopeQuality(Builder $query): Builder
    {
        return $query->whereJsonContains('employee_roles', 'qualita');
    }

    public function scopeAudits(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereJsonContains('employee_roles', 'qualita')
                ->orWhereJsonContains('employee_roles', 'audit');
        });
    }

    public function scopeEmployee(Builder $query): Builder
    {
        return $query->whereJsonContains('employee_roles', 'dipendente');
    }
}

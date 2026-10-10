<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Unico\Core\Models\Employee as CoreEmployee;

class Employee extends CoreEmployee
{
    use HasFactory, SoftDeletes;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

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

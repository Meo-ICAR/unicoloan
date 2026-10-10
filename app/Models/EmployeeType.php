<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\EmployeeType as CoreEmployeeType;

class EmployeeType extends CoreEmployeeType
{
    /**
     * Casting dei tipi di attributi (Sintassi moderna Laravel).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_external' => 'boolean',
        ]);
    }

    /* =========================================================================
     | RELAZIONI / QUERY ASSOCIATE
     | ========================================================================= */

    /**
     * Restituisce la query per ottenere gli impiegati che hanno questo ruolo.
     * Esegue una ricerca JSON nel campo 'employee_roles' del Model Employee.
     */
    public function employees(): Builder
    {
        return Employee::whereJsonContains('employee_roles', $this->name);
    }

    /* =========================================================================
     | SCOPES
     | ========================================================================= */

    /**
     * Scope per filtrare per ruoli esterni o interni.
     */
    public function scopeExternal(Builder $query, bool $isExternal = true): Builder
    {
        return $query->where('is_external', $isExternal);
    }

    /**
     * Scope per filtrare per tipo azienda (es. FINANCE).
     */
    public function scopeForCompanyType(Builder $query, string $companyType): Builder
    {
        return $query->where('companytype', $companyType);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(EmployeeTypePermission::class, 'employee_type_id');
    }
}

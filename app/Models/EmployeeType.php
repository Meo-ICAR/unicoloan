<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeType extends Model
{
    protected $connection = 'mysql_unicobpm';

    /*
      'dipendente' => 'Dipendente',
                                'istruttore' => 'Istruttore',
                                'amministrativo' => 'Amministrativo',
                                'segreteria' => 'Segreteria',
                                'quality' => 'Qualita',
                                'reclami' => 'Reclami',
                                'dpo' => 'Privacy',
                                'cda' => 'CdA',
                                'compliance' => 'Compliance',
                                'internal audit' => 'Auditor',
                                'AML' => 'AML',
                                'SOS' => 'Resp. SOS',
                                'legale' => 'Legale',
        */
    /**
     * I campi che possono essere assegnati in massa (mass assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'icon',
        'companytype',
        'is_external',
    ];

    /**
     * Casting dei tipi di attributi (Sintassi moderna Laravel).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
        ];
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

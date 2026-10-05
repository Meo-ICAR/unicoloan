<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    use HasFactory;

    protected $connection = 'mysql_unicobpm';

    public const PLAN_BASE = 'BASE';

    public const PLAN_MEDIUM = 'MEDIUM';

    public const PLAN_FULL = 'FULL';

    public const PLAN_LEVELS = [
        self::PLAN_BASE => 1,
        self::PLAN_MEDIUM => 2,
        self::PLAN_FULL => 3,
    ];

    protected $fillable = [
        'app_name',
        'key',
        'name',
        'group',
        'min_plan',
    ];

    /**
     * Scope per filtrare solo le risorse dell'applicazione corrente.
     */
    public function scopeForCurrentApp(Builder $query): Builder
    {
        return $query->where('app_name', config('app.name'));
    }

    /**
     * Verifica se il piano del tenant (aziendale) soddisfa il piano minimo della risorsa.
     */
    public function isAccessibleWithPlan(string $companyPlan): bool
    {
        $currentPlanLevel = self::PLAN_LEVELS[strtoupper($companyPlan)] ?? 0;
        $requiredPlanLevel = self::PLAN_LEVELS[$this->min_plan] ?? 1;

        return $currentPlanLevel >= $requiredPlanLevel;
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(EmployeeTypePermission::class, 'resource_id');
    }
}

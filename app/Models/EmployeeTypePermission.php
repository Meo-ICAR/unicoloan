<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\EmployeeTypePermission as CoreEmployeeTypePermission;

class EmployeeTypePermission extends CoreEmployeeTypePermission
{
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}

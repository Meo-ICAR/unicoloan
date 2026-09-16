<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTypePermission extends Model
{
    protected $connection = 'mysql_unicobpm';

    protected $fillable = [
        'employee_type_id',
        'resource_id',
        'action',
    ];

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}

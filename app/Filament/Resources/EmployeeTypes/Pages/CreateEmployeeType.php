<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeType extends CreateRecord
{
    protected static string $resource = EmployeeTypeResource::class;
}

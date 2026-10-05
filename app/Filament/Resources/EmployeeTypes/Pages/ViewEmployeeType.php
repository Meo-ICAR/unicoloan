<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeType extends ViewRecord
{
    protected static string $resource = EmployeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

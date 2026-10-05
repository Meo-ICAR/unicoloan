<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeType extends EditRecord
{
    protected static string $resource = EmployeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\FornitoriRoles\Pages;

use App\Filament\Resources\FornitoriRoles\FornitoriRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFornitoriRole extends EditRecord
{
    protected static string $resource = FornitoriRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

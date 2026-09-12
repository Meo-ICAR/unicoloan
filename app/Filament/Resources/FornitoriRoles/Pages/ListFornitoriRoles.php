<?php

namespace App\Filament\Resources\FornitoriRoles\Pages;

use App\Filament\Resources\FornitoriRoles\FornitoriRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFornitoriRoles extends ListRecords
{
    protected static string $resource = FornitoriRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

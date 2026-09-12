<?php

namespace App\Filament\Unicofin\Resources\FornitoriRoles\Pages;

use App\Filament\Unicofin\Resources\FornitoriRoles\FornitoriRoleResource;
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

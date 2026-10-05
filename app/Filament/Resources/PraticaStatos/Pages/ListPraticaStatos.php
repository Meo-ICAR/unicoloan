<?php

namespace App\Filament\Resources\PraticaStatos\Pages;

use App\Filament\Resources\PraticaStatos\PraticaStatoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticaStatos extends ListRecords
{
    protected static string $resource = PraticaStatoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

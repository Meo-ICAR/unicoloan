<?php

namespace App\Filament\Unicofin\Resources\PraticaStatos\Pages;

use App\Filament\Unicofin\Resources\PraticaStatos\PraticaStatoResource;
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

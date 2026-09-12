<?php

namespace App\Filament\Unicofin\Resources\Praticas\Pages;

use App\Filament\Unicofin\Resources\Praticas\PraticaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticas extends ListRecords
{
    protected static string $resource = PraticaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitos\Pages;

use App\Filament\Unicofin\Resources\PraticaRequisitos\PraticaRequisitoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticaRequisitos extends ListRecords
{
    protected static string $resource = PraticaRequisitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

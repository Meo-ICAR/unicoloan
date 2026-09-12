<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Pages;

use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\PraticaRequisitoOperativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticaRequisitoOperativos extends ListRecords
{
    protected static string $resource = PraticaRequisitoOperativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

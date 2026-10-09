<?php

namespace App\Filament\Agenti\Resources\Pratiche\Pages;

use App\Filament\Agenti\Resources\Pratiche\PraticaAgenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPraticheAgente extends ListRecords
{
    protected static string $resource = PraticaAgenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuova pratica'),
        ];
    }
}

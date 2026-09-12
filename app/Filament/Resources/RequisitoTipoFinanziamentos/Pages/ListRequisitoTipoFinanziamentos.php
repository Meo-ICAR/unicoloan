<?php

namespace App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\Pages;

use App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\RequisitoTipoFinanziamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequisitoTipoFinanziamentos extends ListRecords
{
    protected static string $resource = RequisitoTipoFinanziamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

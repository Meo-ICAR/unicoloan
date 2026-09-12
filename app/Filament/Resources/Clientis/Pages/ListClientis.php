<?php

namespace App\Filament\Unicofin\Resources\Clientis\Pages;

use App\Filament\Unicofin\Resources\Clientis\ClientiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientis extends ListRecords
{
    protected static string $resource = ClientiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

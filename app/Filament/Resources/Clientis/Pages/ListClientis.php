<?php

namespace App\Filament\Resources\Clientis\Pages;

use App\Filament\Resources\Clientis\ClientiResource;
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

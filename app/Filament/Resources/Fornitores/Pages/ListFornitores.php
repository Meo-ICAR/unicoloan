<?php

namespace App\Filament\Unicofin\Resources\Fornitores\Pages;

use App\Filament\Unicofin\Resources\Fornitores\FornitoreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFornitores extends ListRecords
{
    protected static string $resource = FornitoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('On Boarding'),
        ];
    }
}

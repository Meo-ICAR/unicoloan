<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Actions\CreaMandatoPraticaAction;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreaMandatoPraticaAction::make(),
            DeleteAction::make(),
        ];
    }
}

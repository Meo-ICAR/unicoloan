<?php

namespace App\Filament\Unicofin\Resources\Clientis\Pages;

use App\Filament\Unicofin\Resources\Clientis\ClientiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClienti extends EditRecord
{
    protected static string $resource = ClientiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

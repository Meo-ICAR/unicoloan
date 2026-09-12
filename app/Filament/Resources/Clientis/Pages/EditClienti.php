<?php

namespace App\Filament\Resources\Clientis\Pages;

use App\Filament\Actions\BpmActivitiesAction;
use App\Filament\Resources\Clientis\ClientiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClienti extends EditRecord
{
    protected static string $resource = ClientiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BpmActivitiesAction::make()->modelType('cliente'),
            DeleteAction::make(),
        ];
    }
}

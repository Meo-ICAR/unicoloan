<?php

namespace App\Filament\Resources\Fornitores\Pages;

use App\Filament\Actions\BpmActivitiesAction;
use App\Filament\Resources\Fornitores\FornitoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFornitore extends EditRecord
{
    protected static string $resource = FornitoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BpmActivitiesAction::make()->modelType('fornitore'),
            DeleteAction::make(),
        ];
    }
}

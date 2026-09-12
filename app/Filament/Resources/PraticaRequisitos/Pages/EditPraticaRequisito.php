<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitos\Pages;

use App\Filament\Unicofin\Resources\PraticaRequisitos\PraticaRequisitoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPraticaRequisito extends EditRecord
{
    protected static string $resource = PraticaRequisitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

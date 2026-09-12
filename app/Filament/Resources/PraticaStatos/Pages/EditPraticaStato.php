<?php

namespace App\Filament\Resources\PraticaStatos\Pages;

use App\Filament\Resources\PraticaStatos\PraticaStatoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPraticaStato extends EditRecord
{
    protected static string $resource = PraticaStatoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

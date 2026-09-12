<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Pages;

use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\PraticaRequisitoOperativoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPraticaRequisitoOperativo extends EditRecord
{
    protected static string $resource = PraticaRequisitoOperativoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

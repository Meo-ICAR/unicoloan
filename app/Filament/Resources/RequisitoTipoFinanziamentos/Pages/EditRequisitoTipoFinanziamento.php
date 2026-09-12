<?php

namespace App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\Pages;

use App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\RequisitoTipoFinanziamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequisitoTipoFinanziamento extends EditRecord
{
    protected static string $resource = RequisitoTipoFinanziamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

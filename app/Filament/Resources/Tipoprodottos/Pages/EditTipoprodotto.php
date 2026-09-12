<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\Pages;

use App\Filament\Unicofin\Resources\Tipoprodottos\TipoprodottoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoprodotto extends EditRecord
{
    protected static string $resource = TipoprodottoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Tipoprodottos\Pages;

use App\Filament\Resources\Tipoprodottos\TipoprodottoResource;
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

<?php

namespace App\Filament\Resources\TipoprodottoSubs\Pages;

use App\Filament\Resources\TipoprodottoSubs\TipoprodottoSubResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoprodottoSub extends EditRecord
{
    protected static string $resource = TipoprodottoSubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

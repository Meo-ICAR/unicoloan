<?php

namespace App\Filament\Resources\TipoprodottoSubs\Pages;

use App\Filament\Resources\TipoprodottoSubs\TipoprodottoSubResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTipoprodottoSubs extends ListRecords
{
    protected static string $resource = TipoprodottoSubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

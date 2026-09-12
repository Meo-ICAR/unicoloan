<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\Pages;

use App\Filament\Unicofin\Resources\Tipoprodottos\TipoprodottoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTipoprodottos extends ListRecords
{
    protected static string $resource = TipoprodottoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

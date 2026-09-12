<?php

namespace App\Filament\Resources\Tipoprodottos\Pages;

use App\Filament\Resources\Tipoprodottos\TipoprodottoResource;
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

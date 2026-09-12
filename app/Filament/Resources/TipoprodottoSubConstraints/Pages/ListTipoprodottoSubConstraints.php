<?php

namespace App\Filament\Resources\TipoprodottoSubConstraints\Pages;

use App\Filament\Resources\TipoprodottoSubConstraints\TipoprodottoSubConstraintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTipoprodottoSubConstraints extends ListRecords
{
    protected static string $resource = TipoprodottoSubConstraintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

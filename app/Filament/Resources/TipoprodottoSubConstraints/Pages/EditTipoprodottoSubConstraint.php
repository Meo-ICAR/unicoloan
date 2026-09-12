<?php

namespace App\Filament\Resources\TipoprodottoSubConstraints\Pages;

use App\Filament\Resources\TipoprodottoSubConstraints\TipoprodottoSubConstraintResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoprodottoSubConstraint extends EditRecord
{
    protected static string $resource = TipoprodottoSubConstraintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

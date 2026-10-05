<?php

namespace App\Filament\Resources\ProvvigioniRules\Pages;

use App\Filament\Resources\ProvvigioniRules\ProvvigioniRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProvvigioniRule extends EditRecord
{
    protected static string $resource = ProvvigioniRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ProvvigioniRules\Pages;

use App\Filament\Resources\ProvvigioniRules\ProvvigioniRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProvvigioniRules extends ListRecords
{
    protected static string $resource = ProvvigioniRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

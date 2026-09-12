<?php

namespace App\Filament\Unicofin\Resources\ProvvigioniRules\Pages;

use App\Filament\Unicofin\Resources\ProvvigioniRules\ProvvigioniRuleResource;
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

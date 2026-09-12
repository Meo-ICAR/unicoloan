<?php

namespace App\Filament\Unicofin\Resources\Clientis\RelationManagers;

use App\Filament\Unicofin\Resources\ProvvigioniRules\ProvvigioniRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    protected static ?string $relatedResource = ProvvigioniRuleResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Clientis\RelationManagers;

use App\Filament\Resources\ProvvigioniRules\ProvvigioniRuleResource;
use App\Filament\Traits\HasRelationPlanAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ProvvigioniRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

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

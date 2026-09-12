<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubs\RelationManagers;

use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\TipoprodottoSubConstraintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LimitsRelationManager extends RelationManager
{
    protected static string $relationship = 'limits';

    protected static ?string $relatedResource = TipoprodottoSubConstraintResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

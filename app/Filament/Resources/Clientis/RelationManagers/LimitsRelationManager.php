<?php

namespace App\Filament\Resources\Clientis\RelationManagers;

use App\Filament\Resources\TipoprodottoSubConstraints\TipoprodottoSubConstraintResource;
use App\Filament\Traits\HasRelationPlanAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LimitsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

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

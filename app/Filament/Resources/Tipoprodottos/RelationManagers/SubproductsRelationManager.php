<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\RelationManagers;

use App\Filament\Unicofin\Resources\TipoprodottoSubs\Tables\TipoprodottoSubsTable;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\TipoprodottoSubResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SubproductsRelationManager extends RelationManager
{
    protected static string $relationship = 'subproducts';

    protected static ?string $relatedResource = TipoprodottoSubResource::class;

    public function table(Table $table): Table
    {
        return TipoprodottoSubsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

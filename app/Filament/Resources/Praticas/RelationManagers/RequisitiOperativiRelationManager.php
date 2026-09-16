<?php

namespace App\Filament\Resources\Praticas\RelationManagers;

use App\Filament\Resources\PraticaRequisitoOperativos\PraticaRequisitoOperativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RequisitiOperativiRelationManager extends RelationManager
{
    protected static string $relationship = 'requisitiOperativi';

    protected static ?string $title = 'Requisiti Operativi';

    protected static ?string $relatedResource = PraticaRequisitoOperativoResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

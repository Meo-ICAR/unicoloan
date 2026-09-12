<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PraticaRequisitoOperativosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pratica.id')
                    ->searchable(),
                TextColumn::make('pratica_requisito_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stato')
                    ->searchable(),
                TextColumn::make('data_richiesta')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('data_completamento')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

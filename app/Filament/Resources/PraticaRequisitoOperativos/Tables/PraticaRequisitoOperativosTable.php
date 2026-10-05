<?php

namespace App\Filament\Resources\PraticaRequisitoOperativos\Tables;

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
                    ->label('Pratica')
                    ->searchable(),
                TextColumn::make('pratica_requisito_id')
                    ->label('Requisito pratica')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->searchable(),
                TextColumn::make('data_richiesta')
                    ->label('Data richiesta')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('data_completamento')
                    ->label('Data completamento')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
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

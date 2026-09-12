<?php

namespace App\Filament\Unicofin\Resources\ProvvigioniRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProvvigioniRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipoprodotto.name')
                    ->searchable(),
                TextColumn::make('tipoprodottoSub.name')
                    ->searchable(),
                TextColumn::make('clienti_id')
                    ->searchable(),
                TextColumn::make('kind.name')
                    ->searchable(),
                TextColumn::make('fornitori_id')
                    ->searchable(),
                IconColumn::make('coordinamento')
                    ->boolean(),
                IconColumn::make('iscliente')
                    ->boolean(),
                TextColumn::make('tipo_provvigioni')
                    ->searchable(),
                TextColumn::make('value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->date()
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

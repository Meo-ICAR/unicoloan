<?php

namespace App\Filament\Resources\RequisitoTipoFinanziamentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RequisitoTipoFinanziamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipoprodotto_id')
                    ->label('Tipo prodotto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipoprodotto_sub_id')
                    ->label('Sottotipo prodotto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pratica_requisito_id')
                    ->label('Requisito pratica')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('obbligatorio')
                    ->label('Obbligatorio')
                    ->boolean(),
                TextColumn::make('ordine')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
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

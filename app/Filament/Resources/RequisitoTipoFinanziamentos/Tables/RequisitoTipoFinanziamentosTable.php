<?php

namespace App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\Tables;

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
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipoprodotto_sub_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pratica_requisito_id')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('obbligatorio')
                    ->boolean(),
                TextColumn::make('ordine')
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

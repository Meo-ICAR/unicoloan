<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TipoprodottosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                ToggleColumn::make('is_active'),
                TextColumn::make('code')
                    ->searchable(),
                IconColumn::make('is_external')
                    ->boolean(),
                IconColumn::make('is_oneclient')
                    ->boolean(),
                TextColumn::make('oam')
                    ->searchable(),
                TextColumn::make('tipo_provvigioni')
                    ->badge(),

            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
                TernaryFilter::make('is_external'),
                // ->default(true),
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

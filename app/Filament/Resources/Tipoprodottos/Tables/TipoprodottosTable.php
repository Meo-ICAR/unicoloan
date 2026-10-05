<?php

namespace App\Filament\Resources\Tipoprodottos\Tables;

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
                    ->label('Nome')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Attivo'),
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable(),
                IconColumn::make('is_external')
                    ->label('Esterno')
                    ->boolean(),
                IconColumn::make('is_oneclient')
                    ->label('Mono-Cliente')
                    ->boolean(),
                TextColumn::make('oam')
                    ->label('Codice OAM')
                    ->searchable(),
                TextColumn::make('tipo_provvigioni')
                    ->label('Tipo Provvigione')
                    ->badge(),

            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
                TernaryFilter::make('is_external')
                    ->label('Esterno'),
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

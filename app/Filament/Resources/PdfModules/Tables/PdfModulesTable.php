<?php

namespace App\Filament\Resources\PdfModules\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PdfModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Modulo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('documentType.name')
                    ->label('Tipo documento')
                    ->placeholder('Non collegato'),
                TextColumn::make('client_scope')
                    ->label('Ambito')
                    ->badge(),
                TextColumn::make('tipi_prodotto')
                    ->label('Prodotti')
                    ->badge()
                    ->placeholder('Tutti'),
                TextColumn::make('fields_count')
                    ->label('Campi')
                    ->counts('fields'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

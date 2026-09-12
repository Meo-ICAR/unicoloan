<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TipoprodottoSubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Colonna per la relazione con la tabella madre 'tipoprodotto'
                TextColumn::make('tipoProdotto.name')
                    ->label('Prodotto Principale')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('name')
                    ->label('Nome Sottoprodotto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                ToggleColumn::make('is_active'),
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('vincoli')
                    ->label('Vincoli / Note')
                    ->limit(40)
                    ->searchable()
                    // Mostra il testo intero al passaggio del mouse se viene troncato
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return $state;
                    }),
            ])
            ->filters([
                Filter::make('is_active')
                    ->default(true),
                SelectFilter::make('tipoprodotto_id')
                    ->relationship('tipoProdotto', 'name')
                    ->label('Prodotto Principale')
                    ->searchable()
                    ->preload(),
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

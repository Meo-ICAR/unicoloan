<?php

namespace App\Filament\Resources\TipoprodottoSubConstraints\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class TipoprodottoSubConstraintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipoprodotto_sub_id')
                    ->label('Sottoprodotto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clienti_id')
                    ->label('Banca')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->label('Ruolo')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Attivo'),
                TextColumn::make('min_age')
                    ->label('Età Minima')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_age_at_maturity')
                    ->label('Età Massima a Scadenza')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_amount')
                    ->label('Importo Minimo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_amount')
                    ->label('Importo Massimo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_duration_months')
                    ->label('Durata Minima (mesi)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_duration_months')
                    ->label('Durata Massima (mesi)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_employment_months')
                    ->label('Anzianità Lavorativa Minima (mesi)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_debt_to_income_ratio')
                    ->label('Rapporto Rata/Reddito Massimo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_ltv_percentage')
                    ->label('LTV Massimo (%)')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('is_active')
                    ->label('Attivo')
                    ->default(true),
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

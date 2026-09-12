<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Tables;

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
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipoprodotto_sub_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clienti_id')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->searchable(),
                ToggleColumn::make('is_active'),
                TextColumn::make('min_age')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_age_at_maturity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_duration_months')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_duration_months')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_employment_months')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_debt_to_income_ratio')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_ltv_percentage')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('is_active')
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

<?php

namespace App\Filament\Resources\ProvvigioniRules\Tables;

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
                    ->label('Tipo Prodotto')
                    ->searchable(),
                TextColumn::make('tipoprodottoSub.name')
                    ->label('Sottotipo Prodotto')
                    ->searchable(),
                TextColumn::make('clienti_id')
                    ->label('Banca')
                    ->searchable(),
                TextColumn::make('kind.name')
                    ->label('Ruolo Fornitore')
                    ->searchable(),
                TextColumn::make('fornitori_id')
                    ->label('Agente')
                    ->searchable(),
                IconColumn::make('coordinamento')
                    ->label('Coordinamento')
                    ->boolean(),
                IconColumn::make('iscliente')
                    ->label('È Cliente')
                    ->boolean(),
                TextColumn::make('tipo_provvigioni')
                    ->label('Tipo Provvigione')
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Valore')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->label('Valido Dal')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Valido Al')
                    ->date()
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

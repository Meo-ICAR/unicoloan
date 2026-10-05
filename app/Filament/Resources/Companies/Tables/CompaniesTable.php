<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Ragione sociale')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->label('Partita IVA / C.F.')
                    ->searchable(),
                TextColumn::make('vat_name')
                    ->label('Denominazione fiscale')
                    ->searchable(),
                TextColumn::make('oam')
                    ->label('N. iscrizione OAM')
                    ->searchable(),
                TextColumn::make('oam_at')
                    ->label('Data iscrizione OAM')
                    ->date('d/m/y')
                    ->sortable(),
                TextColumn::make('oam_name')
                    ->label('Nome OAM')
                    ->searchable(),
                TextColumn::make('numero_iscrizione_rui')
                    ->label('N. iscrizione RUI')
                    ->searchable(),
                TextColumn::make('ivass')
                    ->label('Codice IVASS')
                    ->searchable(),
                TextColumn::make('ivass_at')
                    ->label('Data iscrizione IVASS')
                    ->date('d/m/y')
                    ->sortable(),
                TextColumn::make('ivass_name')
                    ->label('Nome IVASS')
                    ->searchable(),
                TextColumn::make('ivass_section')
                    ->label('Sezione IVASS')
                    ->badge(),
                TextColumn::make('sponsor')
                    ->label('Sponsor')
                    ->searchable(),
                TextColumn::make('company_type')
                    ->label('Tipo società')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime('d/m/Y H:i')
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

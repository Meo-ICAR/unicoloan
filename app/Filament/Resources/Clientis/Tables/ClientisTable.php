<?php

namespace App\Filament\Unicofin\Resources\Clientis\Tables;

use App\Filament\Exports\DynamicGroupExport;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class ClientisTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->defaultSort('nome')
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make(),
                        //    ->groupBy('Produttore')  // Campo per il raggruppamento
                        //    ->sumColumns(['Provvigione']),  // Campi da sommare
                    ])
                    ->label('Esporta Excel')
                    ->color('success'),
            ])
            ->columns([

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->label('Ragione Sociale'),
                TextColumn::make('piva')
                    ->searchable()
                    ->sortable()
                    ->label('Partita IVA'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Istruttoria'),
                TextColumn::make('principal_type')
                    ->sortable()
                    ->badge()
                    ->colors([
                        'primary' => 'Banca',
                        'warning' => 'Broker',
                        'success' => 'Captive',
                    ])
                    ->label('Tipo'),
                TextColumn::make('stipulated_at')
                    ->date('d/m/y')
                    ->sortable()
                    ->label('Stipula'),

            ])
            ->filters([

                SelectFilter::make('principal_type')
                    ->options([
                        '--' => '---',
                        'banca' => 'Banca',
                        'broker' => 'Broker',
                        'captive' => 'Broker Captive',
                        'assicurazione' => 'Assicurazione',
                    ])
                    ->default('banca')
                    ->label('Tipo Mandante'),
                SelectFilter::make('ivass_section')
                    ->options([
                        'A' => 'Sezione A',
                        'B' => 'Sezione B',
                        'C' => 'Sezione C',
                        'D' => 'Sezione D',
                        'E' => 'Sezione E',
                    ])
                    ->label('Sez. IVASS'),
            ])
            ->actions([
                //  ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                // DeleteBulkAction::make(),
            ]);
    }
}

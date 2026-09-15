<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Praticas\PraticaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ClientPratichesRelationManager extends RelationManager
{
    protected static string $relationship = 'clientPratiches';

    protected static ?string $relatedResource = PraticaResource::class;

    public function table(Table $table): Table
    {

        return $table
            ->reorderableColumns()
            ->defaultSort('tipo_prodotto')
            ->columns([
                TextColumn::make('tipo_prodotto')
                    ->label('Tipo Prodotto')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('denominazione_banca')
                    ->label('Banca')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('stato_pratica')
                    ->label('Stato Pratica')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('denominazione_agente')
                    ->label('Produttore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('data_inserimento_pratica')
                    ->label('Data Inserimento')
                    ->date()
                    ->sortable()
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

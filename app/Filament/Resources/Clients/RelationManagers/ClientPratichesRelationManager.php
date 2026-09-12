<?php

namespace App\Filament\Unicofin\Resources\Clients\RelationManagers;

use App\Filament\Unicofin\Resources\Praticas\PraticaResource;
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
                    ->sortable()
                    ->searchable(),

                TextColumn::make('denominazione_banca')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('stato_pratica')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('denominazione_agente')
                    ->label('Produttore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('data_inserimento_pratica')
                    ->date()
                    ->sortable()
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}

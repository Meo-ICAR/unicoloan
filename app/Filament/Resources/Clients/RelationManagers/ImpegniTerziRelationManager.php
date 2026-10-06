<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Impegni di terzi del cliente (finanziamenti in corso presso altri istituti): sono righe di
 * `pratiches` con `is_notowned`, ma non sono pratiche di lavorazione.
 */
class ImpegniTerziRelationManager extends RelationManager
{
    protected static string $relationship = 'thirdPartyFinancings';

    protected static ?string $title = 'Impegni di terzi';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codice_pratica')
            ->columns([
                TextColumn::make('denominazione_banca')->label('Banca')->searchable(),
                TextColumn::make('denominazione_prodotto')->label('Prodotto'),
                TextColumn::make('rata')->label('Rata')->money('EUR'),
                TextColumn::make('nrate')->label('Numero rate'),
                TextColumn::make('amount')->label('Importo')->money('EUR'),
                TextColumn::make('data_inserimento_pratica')->label('Data')->date(),
            ]);
    }
}

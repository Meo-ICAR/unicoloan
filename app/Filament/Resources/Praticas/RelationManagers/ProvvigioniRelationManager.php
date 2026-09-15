<?php

namespace App\Filament\Resources\Praticas\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('denominazione_riferimento')
                    ->label('Produttore'),
                TextInput::make('importo')
                    ->label('Importo'),
                //  ->money('EUR')
                // ->alignEnd()
                TextInput::make('descrizione')
                    ->label('Descrizione')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('entrata_uscita')
                    ->label('Entrata/Uscita'),
                TextEntry::make('segnalatore')
                    ->label('Segnalatore'),
                TextEntry::make('importo')
                    ->label('Importo')
                    ->money('EUR')
                    ->alignEnd(),
                TextEntry::make('descrizione')
                    ->label('Descrizione'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderableColumns()
            ->recordTitleAttribute('Provvigioni associate alla pratica')
            ->columns([
                TextColumn::make('entrata_uscita')
                    ->label('Entrata/Uscita')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Entrata' => 'success',
                        'Uscita' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('denominazione_riferimento')
                    ->label('Produttore'),
                TextColumn::make('importo')
                    ->label('Importo')
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('descrizione')
                    ->label('Descrizione'),

                TextColumn::make('status_compenso')
                    ->label('Stato Compenso'),
                TextColumn::make('data_status')
                    ->label('Data Stato')
                    ->date(),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                //  CreateAction::make(),
                //   AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),

            ], position: RecordActionsPosition::BeforeColumns);
    }
}

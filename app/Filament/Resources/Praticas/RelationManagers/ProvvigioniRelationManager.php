<?php

namespace App\Filament\Unicofin\Resources\Praticas\RelationManagers;

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
                TextInput::make('denominazione_riferimento'),
                TextInput::make('importo'),
                //  ->money('EUR')
                // ->alignEnd()
                TextInput::make('descrizione')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('entrata_uscita'),
                TextEntry::make('segnalatore'),
                TextEntry::make('importo')
                    ->money('EUR')
                    ->alignEnd(),
                TextEntry::make('descrizione'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderableColumns()
            ->recordTitleAttribute('Provvigioni associate alla pratica')
            ->columns([
                TextColumn::make('entrata_uscita')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Entrata' => 'success',
                        'Uscita' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('denominazione_riferimento')
                    ->label('Produttore'),
                TextColumn::make('importo')
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('descrizione'),

                TextColumn::make('status_compenso'),
                TextColumn::make('data_status')
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

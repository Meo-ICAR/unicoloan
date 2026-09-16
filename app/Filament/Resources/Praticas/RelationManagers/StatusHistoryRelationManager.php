<?php

namespace App\Filament\Resources\Praticas\RelationManagers;

use App\Models\PraticaStato;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Storico Stati';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status_from')
                    ->label('Stato Precedente')
                    ->options(fn () => PraticaStato::pluck('name', 'name'))
                    ->searchable(),
                Select::make('status_to')
                    ->label('Nuovo Stato')
                    ->options(fn () => PraticaStato::pluck('name', 'name'))
                    ->searchable()
                    ->required(),
                DateTimePicker::make('changed_at')
                    ->label('Data Cambio Stato')
                    ->default(now())
                    ->required(),
                TextInput::make('source')
                    ->label('Origine')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status_to')
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('status_from')
                    ->label('Da')
                    ->placeholder('—'),
                TextColumn::make('status_to')
                    ->label('A')
                    ->badge(),
                TextColumn::make('changed_at')
                    ->label('Data Cambio')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Origine'),
                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(50),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Fornitores\RelationManagers;

use App\Models\PROFORMA\Clienti;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlacklistRelationManager extends RelationManager
{
    protected static string $relationship = 'blacklistRecords';

    protected static ?string $title = 'Blacklist Banche';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cliente_id')
                    ->label('Banca')
                    ->options(Clienti::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Textarea::make('motivo')
                    ->label('Motivazione del Blocco')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                DatePicker::make('data_inizio')
                    ->label('Data Inizio Blocco')
                    ->default(now()),
                DatePicker::make('data_fine')
                    ->label('Data Fine Blocco (Opzionale)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motivo')
            ->columns([
                TextColumn::make('cliente.name')
                    ->label('Banca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(50),
                TextColumn::make('data_inizio')
                    ->label('Data Inizio')
                    ->date(),
                TextColumn::make('data_fine')
                    ->label('Data Fine')
                    ->date()
                    ->placeholder('Indeterminato'),
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

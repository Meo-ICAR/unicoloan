<?php

namespace App\Filament\Resources\Clientis\RelationManagers;

use App\Models\Employee;
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

class DipendentiBlacklistatiRelationManager extends RelationManager
{
    protected static string $relationship = 'dipendentiBlacklistati';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Dipendente')
                    ->options(Employee::pluck('name', 'id'))
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
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Dipendente'),
                TextColumn::make('motivo')
                    ->limit(50),
                TextColumn::make('data_inizio')
                    ->date(),
                TextColumn::make('data_fine')
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

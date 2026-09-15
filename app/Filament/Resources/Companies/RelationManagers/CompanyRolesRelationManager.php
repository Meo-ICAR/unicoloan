<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyRolesRelationManager extends RelationManager
{
    protected static string $relationship = 'companyRoles';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Ruoli';

    protected static ?string $title = 'Ruoli';

    protected static ?string $modelLabel = 'Ruolo';

    protected static ?string $pluralModelLabel = 'Ruoli';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome'),
                Select::make('funzione')
                    ->label('Funzione')
                    ->options([
                        'INTERNAL AUDIT' => 'Audit Interno',
                        'COMPLIANCE' => 'Compliance',
                        'DATA PROTECTION' => 'Protezione Dati',
                        'AML' => 'AML',
                        'ALTRO' => 'Altro',
                    ]),
                Select::make('execution_method')
                    ->label('Metodo di esecuzione')
                    ->options(['audit' => 'audit', 'ispezione' => 'ispezione', 'altro' => 'Altro']),
                Toggle::make('is_external')
                    ->label('Esterno'),
                TextInput::make('expertName')
                    ->label('Incaricato'),
                DatePicker::make('dal')
                    ->label('Dal'),
                DatePicker::make('al')
                    ->label('Al'),

                TextInput::make('n')
                    ->label('Numero')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('funzione')
                    ->label('Funzione')
                    ->badge(),
                IconColumn::make('is_external')
                    ->label('Esterno')
                    ->boolean(),
                TextColumn::make('dal')
                    ->label('Dal')
                    ->date()
                    ->sortable(),
                TextColumn::make('al')
                    ->label('Al')
                    ->date()
                    ->sortable(),
                TextColumn::make('execution_method')
                    ->label('Metodo di esecuzione')
                    ->badge(),
                TextColumn::make('expertName')
                    ->label('Nome esperto')
                    ->searchable(),
                TextColumn::make('n')
                    ->label('Numero')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
                //  AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                //    DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //  DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

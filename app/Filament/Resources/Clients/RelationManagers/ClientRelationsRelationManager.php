<?php

namespace App\Filament\Unicofin\Resources\Clients\RelationManagers;

use App\Models\ClientRelation;
use App\Models\ClientType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class ClientRelationsRelationManager extends RelationManager
{
    protected static string $relationship = 'companyRelations';

    protected static ?string $title = 'Cariche sociali';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Mostra la relazione solo se il cliente è una società (persona giuridica)
        return !$ownerRecord->is_person;
    }

    public function table(Tables\Table $table): Table
    {
        return $table
            ->recordTitleAttribute('client.name')
            ->modifyQueryUsing(fn($query) => $query->with(['clientType' => fn($q) => $q->where('is_company', true)]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Persona')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clientType.name')
                    ->label('Ruolo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shares_percentage')
                    ->label('Quote %')
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('is_titolare')
                    ->label('Titolare')
                    ->boolean(),
                TextColumn::make('data_inizio_ruolo')
                    ->label('Inizio Ruolo')
                    ->date()
                    ->sortable(),
                TextColumn::make('data_fine_ruolo')
                    ->label('Fine Ruolo')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Data Creazione')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Data Aggiornamento')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuova Relazione'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Modifica'),
                DeleteAction::make()
                    ->label('Elimina'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Elimina Selezionati'),
                ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Persona')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('client_type_id')
                    ->label('Ruolo')
                    ->options(function () {
                        return ClientType::where('is_company', true)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                Toggle::make('is_titolare')
                    ->label('Titolare')
                    ->default(false),
                TextInput::make('shares_percentage')
                    ->label('Quote (%)')
                    ->numeric()
                    ->suffix('%')
                    ->max(100)
                    ->step(0.01),
                DatePicker::make('data_inizio_ruolo')
                    ->label('Inizio Ruolo'),
                DatePicker::make('data_fine_ruolo')
                    ->label('Fine Ruolo'),
            ]);
    }
}

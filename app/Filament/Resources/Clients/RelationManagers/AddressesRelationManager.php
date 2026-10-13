<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Indirizzi della persona fisica (residenza, domicilio, ...): per le persone giuridiche valgono le sedi.
 */
class AddressesRelationManager extends RelationManager
{
    use HasRelationPlanAccess {
        canViewForRecord as protected planCanViewForRecord;
    }

    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Indirizzi';

    protected static ?string $modelLabel = 'Indirizzo';

    protected static ?string $pluralModelLabel = 'Indirizzi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Client
            && $ownerRecord->is_person
            && static::planCanViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('address_type_id')
                    ->label('Tipo')
                    ->relationship('addressType', 'name')
                    ->createOptionForm([
                        TextInput::make('name')->label('Tipo (es. Residenza, Domicilio)')->required()->maxLength(255),
                    ])
                    ->preload(),
                TextInput::make('name')->label('Descrizione')->maxLength(255),
                TextInput::make('street')->label('Via / Piazza')->required()->maxLength(255),
                TextInput::make('numero')->label('Civico')->maxLength(255),
                TextInput::make('zip_code')->label('CAP')->maxLength(20),
                TextInput::make('city')->label('Comune')->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street')
            ->columns([
                TextColumn::make('addressType.name')->label('Tipo')->badge()->placeholder('—'),
                TextColumn::make('street')
                    ->label('Indirizzo')
                    ->formatStateUsing(fn (string $state, Model $record): string => trim($state.' '.$record->numero)),
                TextColumn::make('zip_code')->label('CAP')->placeholder('—'),
                TextColumn::make('city')->label('Comune')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

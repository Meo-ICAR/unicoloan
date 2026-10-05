<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope; // <-- Importa il trait

class BranchesRelationManager extends RelationManager
{
    use HasRelationPlanAccess { // <-- Basta questo! Controlla automaticamente checkPiano('websites')
        canViewForRecord as protected planCanViewForRecord;
    }

    protected static string $relationship = 'branches';

    /**
     * Per i clienti (App\Models\Client) le sedi hanno senso solo per le
     * società: se il record è una persona fisica (is_person = true), nascondi
     * la scheda a prescindere dal piano/permesso.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord instanceof Client && $ownerRecord->is_person) {
            return false;
        }

        return static::planCanViewForRecord($ownerRecord, $pageClass);
    }

    protected static ?string $title = 'Sedi';

    protected static ?string $modelLabel = 'Sede';

    protected static ?string $pluralModelLabel = 'Sedi';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Filiale (es. Sede Milano, Ufficio Roma)')
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Indica se la filiale è attiva')
                    ->required(),
                Toggle::make('is_main_office')
                    ->label('Indica se è la Sede Legale/Operativa principale (1 = Sì, 0 = No)')
                    ->required(),
                DateTimePicker::make('founded_at')
                    ->displayFormat('d/m/y')
                    ->label('Data di apertura della filiale'),
                DateTimePicker::make('dismissed_at')
                    ->displayFormat('d/m/y')
                    ->label('Data chiusuradella filiale'),
                TextInput::make('address')
                    ->label('Via / Piazza'),
                TextInput::make('street_number')
                    ->label('Numero civico')
                    ->maxLength(20),
                TextInput::make('city')
                    ->label('Città'),
                TextInput::make('zip_code')
                    ->label('CAP')
                    ->maxLength(10),
                TextInput::make('province')
                    ->label('Provincia'),
                TextInput::make('region')
                    ->label('Regione'),
                TextInput::make('manager_first_name')
                    ->label('Nome del responsabile della filiale'),
                TextInput::make('manager_last_name')
                    ->label('Cognome del responsabile della filiale'),
                TextInput::make('manager_tax_code')
                    ->label('Codice Fiscale del responsabile della filiale'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome della filiale (es. Sede Milano, Ufficio Roma)')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    //   ->boolean()
                    //   ->trueIcon('heroicon-o-check-circle')
                    //   ->falseIcon('heroicon-o-x-circle')
                    ->label('Attivo'),
                IconColumn::make('is_main_office')
                    ->label('Sede principale')
                    ->boolean(),
                TextColumn::make('address')
                    ->label('Indirizzo')
                    ->formatStateUsing(fn ($record) => trim(($record->address ?? '').' '.($record->street_number ?? '')))
                    ->searchable(),
                TextColumn::make('city')
                    ->label('Città')
                    ->searchable(),
                TextColumn::make('province')
                    ->label('Provincia'),
                TextColumn::make('manager_last_name')
                    ->label('Responsabile')
                    ->formatStateUsing(fn ($record) => trim(($record->manager_last_name ?? '').' '.($record->manager_first_name ?? '')))
                    ->searchable(),
                TextColumn::make('founded_at')
                    ->label('Data apertura')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('dismissed_at')
                    ->label('Data chiusura')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('fornitore_count')
                    ->counts('fornitore')
                    ->label('Condiviso con')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                //  DissociateAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}

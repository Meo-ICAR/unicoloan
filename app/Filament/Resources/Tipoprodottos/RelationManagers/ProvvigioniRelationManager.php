<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\RelationManagers;

use App\Models\FornitoriRole; // <--- Corretto namespace da Model a Models
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Corretto: usando relationship() oppure pluck('nome', 'id')
                Select::make('fornitorirole_id')
                    ->label('Tipologia')
                    ->options(FornitoriRole::pluck('code', 'id')) // Sostituisci 'nome' col campo del database se diverso (es. 'name')
                    ->searchable()
                    ->preload(),

                Toggle::make('iscliente')
                    ->label('Riconosciute da cliente'),

                Toggle::make('coordinamento')
                    ->required(),

                Select::make('tipo_provvigioni')
                    ->options([
                        'Lordo' => 'Lordo',
                        'Erogato' => 'Erogato',
                        'Netto' => 'Netto',
                        'Fisso' => 'Fisso',
                        '% Attive' => '% Attive',
                    ])
                    ->required()
                    ->default('Lordo'), // Corretto: 'Lordo' con la L maiuscola per combaciare con le opzioni

                TextInput::make('value')
                    ->numeric()
                    ->default(0.0),

                DatePicker::make('valid_from')
                    ->default(now()),

                DatePicker::make('valid_to'),

                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('tipo_provvigioni')
                    ->searchable(),

                TextColumn::make('fornitoriRole.code')
                    ->label('Tipologia')
                    ->searchable()
                    ->sortable()
                    ->default('-'),
                IconColumn::make('coordinamento')
                    ->boolean(),

                TextColumn::make('value')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('valid_from')
                    ->label('Dal')
                    ->date()
                    ->sortable(),

                TextColumn::make('valid_to')
                    ->label('Al')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('tipoprodotto_sub_id')
                    ->label('Filtra per Sub Prodotto')
                    ->placeholder('Tutti i record')
                    ->trueLabel('Solo con Sub Prodotto (Non NULL)')
                    ->falseLabel('Solo Padre / Senza Sub Prodotto (NULL)')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('tipoprodotto_sub_id'),
                        false: fn (Builder $query) => $query->whereNull('tipoprodotto_sub_id'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubs\RelationManagers;

use App\Models\FornitoriRole;
use Filament\Actions\Action; // <--- Corretto namespace da Model a Models
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
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
use Filament\Tables\Table;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()->tipoProdotto?->tipo_provvigioni ?? 'Lordo')
                    ->required(),

                TextInput::make('value')
                    ->numeric()
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()->tipoProdotto?->value ?? 0.0),

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
                IconColumn::make('iscliente')
                    ->boolean()
                    ->label('Da cliente'),

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
                //
            ])
            ->headerActions([
                Action::make('creaDaPadre')
                    ->label('Crea')
                    ->icon('heroicon-o-plus')
                    ->action(function ($livewire) {
                        $sub = $livewire->getOwnerRecord();
                        $sub->loadMissing('tipoProdotto');
                        $padre = $sub->tipoProdotto;

                        // Inserisce direttamente nel DB i dati ereditati
                        $sub->provvigioni()->create([
                            'tipoprodotto_id' => $sub->tipoprodotto_id,
                            'coordinamento' => $padre?->coordinamento ?? false,
                            'iscliente' => $padre?->iscliente ?? false,
                            'tipo_provvigioni' => $padre?->tipo_provvigioni ?? 'Lordo',
                            'value' => $padre?->value ?? 0.0,
                            'valid_from' => now(),
                        ]);
                    }),

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

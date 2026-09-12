<?php

namespace App\Filament\Unicofin\Resources\FornitoriRoles\RelationManagers;

use App\Models\PROFORMA\Clienti;
use App\Models\Tipoprodotto;
use App\Models\TipoprodottoSub;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProvvigioniRelationManager extends RelationManager
{
    protected static string $relationship = 'provvigioni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipoprodotto_id')
                    ->relationship(
                        name: 'tipoprodotto',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                    )
                    ->searchable()
                    ->preload(),

                Select::make('tipoprodotto_sub_id')
                    ->relationship(
                        name: 'tipoprodottoSub',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                    )
                    ->searchable()
                    ->preload(),

                Toggle::make('iscliente')
                    ->label('Riconosciute da cliente'),
                Select::make('clienti_id')
                    ->label('Istituto')
                    ->relationship(
                        name: 'clienti',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)
                    )
                    ->searchable()
                    ->preload(),
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
                TextColumn::make('tipoprodotto.name')
                    ->label('Prodotto')
                    ->searchable(),
                TextColumn::make('tipoprodottoSub.name')
                    ->label('Sub Prodotto')
                    ->searchable(),
                IconColumn::make('iscliente')
                    ->boolean()
                    ->label('Da cliente'),
                TextColumn::make('cliente.name')
                    ->label('Istituto')
                    ->searchable(),
                IconColumn::make('coordinamento')
                    ->label('Coord')
                    ->boolean(),
                TextColumn::make('tipo_provvigioni')
                    ->searchable(),
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
                SelectFilter::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->options(Tipoprodotto::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('tipoprodotto_sub_id')
                    ->label('Sub Prodotto')
                    ->options(TipoprodottoSub::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('clienti_id')
                    ->label('Istituto')
                    ->options(Clienti::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),
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

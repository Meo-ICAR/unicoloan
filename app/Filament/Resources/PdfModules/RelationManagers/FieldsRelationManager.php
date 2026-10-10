<?php

namespace App\Filament\Resources\PdfModules\RelationManagers;

use Unico\Core\Pdf\ModuleFormatter;
use App\Enums\ModuleSourceKey;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campi del modulo';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pdf_field_name')
            ->defaultSort('id')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('pdf_field_name')
                    ->label('Campo PDF')
                    ->searchable(),
                TextColumn::make('pdf_field_type')
                    ->label('Tipo')
                    ->badge(),
                SelectColumn::make('source_key')
                    ->label('Dato')
                    ->options(ModuleSourceKey::class)
                    ->placeholder('— non compilare —'),
                SelectColumn::make('formatter')
                    ->label('Formato')
                    ->options(ModuleFormatter::class)
                    ->placeholder('— nessuno —'),
                TextInputColumn::make('checkbox_on_value')
                    ->label('Valore "spuntato"'),
                TextInputColumn::make('checkbox_when')
                    ->label('Condizione casella')
                    ->placeholder('es. !client.is_person'),
            ])
            ->filters([
                Filter::make('non_mappati')
                    ->label('Solo non mappati')
                    ->query(fn (Builder $query) => $query->whereNull('source_key')),
            ]);
    }
}

<?php

namespace App\Filament\Unicofin\Resources\Praticas\Tables;

use App\Filament\Exports\DynamicGroupExport;
use App\Models\PraticaStati;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class PraticasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderableColumns()
            ->defaultSort('cognome_cliente')
            ->columns([
                TextColumn::make('cognome_cliente')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nome_cliente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tipo_prodotto')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('denominazione_banca')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('stato_pratica')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('denominazione_agente')
                    ->label('Produttore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('data_inserimento_pratica')
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('erogated_at')
                    ->label('Data Erogazione')
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('codice_pratica')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('denominazione_banca')
                    ->label('Banca')
                    ->options(function () {
                        return Pratica::query()
                            ->whereNotNull('denominazione_banca')
                            ->where('denominazione_banca', '!=', '')
                            ->distinct()
                            ->pluck('denominazione_banca', 'denominazione_banca')
                            ->toArray();
                    })
                    ->searchable() // Opzionale: aggiunge la barra di ricerca nel menu a tendina
                    ->multiple(),  // Opzionale: se vuoi permettere la selezione di più banche
                SelectFilter::make('denominazione_agente')
                    ->label('Banca')
                    ->options(function () {
                        return Pratica::query()
                            ->whereNotNull('denominazione_agente')
                            ->where('denominazione_agente', '!=', '')
                            ->distinct()
                            ->pluck('denominazione_agente', 'denominazione_agente')
                            ->toArray();
                    })
                    ->searchable() // Opzionale: aggiunge la barra di ricerca nel menu a tendina
                    ->multiple(),  // Opzionale: se vuoi permettere la selezione di più banche
                SelectFilter::make('stato_pratica')
                    ->options(PraticaStati::pluck('stato_pratica', 'stato_pratica'))
                    ->multiple()
                    ->label('Escludere)')
                    ->default(['SOSPESA', 'PERFEZIONATA', 'IN AMMORTAMENTO', 'DECLINATA', 'RINUNCIA CLIENTE', 'PRATICA RESPINTA', 'CHIUSA'])
                    ->query(function (Builder $query, array $data): Builder {
                        // Verifica se ci sono valori selezionati nel filtro
                        if (! empty($data['values'])) {
                            // Applica l'esclusione tramite whereNotIn
                            return $query->whereNotIn('stato_pratica', $data['values']);
                        }

                        return $query;
                    }),
                SelectFilter::make('tipo_prodotto')
                    ->options(Tipoprodotto::pluck('tipo_prodotto', 'name'))
                    ->multiple()
                    ->label('Tipo Prodotto'),
                Filter::make('data_inserimento')
                    ->label('Inseriti da 6 mesi')
                    ->default(true)
                    ->query(function (Builder $query): Builder {
                        return $query->where('data_inserimento_pratica', '>', now()->subMonths(6));
                    }),

            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make()
                            ->groupBy('denominazione_riferimento')  // Campo per il raggruppamento
                            ->sumColumns(['importo']),  // Campi da sommare
                    ])
                    ->label('Excel')
                    ->color('success'),
            ])
            ->recordActions([
                // ViewAction::make(),
            ]);
    }
}

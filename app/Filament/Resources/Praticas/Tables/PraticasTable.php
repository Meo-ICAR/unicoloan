<?php

namespace App\Filament\Resources\Praticas\Tables;

use App\Filament\Exports\DynamicGroupExport;
use App\Models\PraticaStati;
use App\Models\PROFORMA\Clienti;
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
            // Gli impegni di terzi (is_notowned) non sono pratiche: si vedono dal cliente.
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_notowned', false))
            ->reorderableColumns()
            ->defaultSort('cognome_cliente')
            ->columns([
                TextColumn::make('cognome_cliente')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nome_cliente')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tipo_prodotto')
                    ->label('Tipo Prodotto')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('denominazione_banca')
                    ->label('Banca')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('stato_pratica')
                    ->label('Stato Pratica')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('denominazione_agente')
                    ->label('Produttore')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('data_inserimento_pratica')
                    ->label('Data Inserimento')
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('erogated_at')
                    ->label('Data Erogazione')
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('rejected_at')
                    ->label('Data Rifiuto')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('codice_pratica')
                    ->label('Codice Pratica')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('not_rejected')
                    ->label('Non respinte')
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->whereNull('rejected_at')),
                Filter::make('not_erogated')
                    ->label('Non erogate')
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->whereNull('erogated_at')),
                SelectFilter::make('istituto_erogazione')
                    ->label('Istituto di erogazione')
                    ->options(fn (): array => Clienti::query()
                        ->where('principal_type', 'banca')
                        ->whereNotNull('name')
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all())
                    ->attribute('denominazione_banca')
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('denominazione_agente')
                    ->label('Produttore')
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
                    ->label('Escludere')
                    ->query(function (Builder $query, array $data): Builder {
                        // Verifica se ci sono valori selezionati nel filtro
                        if (! empty($data['values'])) {
                            // Applica l'esclusione tramite whereNotIn
                            return $query->whereNotIn('stato_pratica', $data['values']);
                        }

                        return $query;
                    }),
                SelectFilter::make('tipo_prodotto')
                    ->options(Tipoprodotto::whereNotNull('tipo_prodotto')->pluck('tipo_prodotto', 'name'))
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

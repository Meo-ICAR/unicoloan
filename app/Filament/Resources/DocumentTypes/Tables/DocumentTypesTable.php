<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ==========================================
                // COLONNE DELLA TABELLA
                // ==========================================
                TextColumn::make('name')
                    ->label('Nome documento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('document_typable')
                    ->label('Modello')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Natura del documento (Flusso) con Badge dedicati
                TextColumn::make('nature')
                    ->label('Natura Flusso')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'incoming' => 'info',
                        'template_fillable' => 'warning',
                        'compliance' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'incoming' => 'Da Ricevere',
                        'template_fillable' => 'Modulo da Compilare',
                        'compliance' => 'Compliance / Regolamento',
                        default => $state,
                    })
                    ->sortable(),
                // Integrazione Durata Leggibile (Ore, Giorni, Mesi, Anni)
                TextColumn::make('duration')
                    ->label('Validità')
                    ->formatStateUsing(function ($record) {
                        if (! $record->duration) {
                            return 'Nessuna scadenza';
                        }

                        $unit = match ($record->duration_unit) {
                            'hours' => 'Ore',
                            'days' => 'Giorni',
                            'months' => 'Mesi',
                            'years' => 'Anni',
                            default => 'Giorni'
                        };

                        return "{$record->duration} {$unit}";
                    })
                    ->placeholder('Senza scadenza')
                    ->sortable(['duration']),
                // Destinatari compattati in un'unica colonna dinamica (Evita 7 colonne booleane)
                TextColumn::make('target')
                    ->label('Applicabile a')
                    ->badge()
                    ->color('gray')
                    ->state(function ($record): array {
                        $targets = [];
                        if ($record->is_person) {
                            $targets[] = 'Persona';
                        }
                        if ($record->is_company) {
                            $targets[] = 'Azienda';
                        }
                        if ($record->is_employee) {
                            $targets[] = 'Dipendente';
                        }
                        if ($record->is_agent) {
                            $targets[] = 'Agente';
                        }
                        if ($record->is_principal) {
                            $targets[] = 'Mandante';
                        }
                        if ($record->is_client) {
                            $targets[] = 'Cliente';
                        }
                        if ($record->is_practice) {
                            $targets[] = 'Pratica';
                        }

                        return $targets;
                    })
                    ->placeholder('Nessun target'),
                // Icone di stato rapide
                IconColumn::make('is_monitored')
                    ->label('Monitorato')
                    ->sortable()
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('doctype')
                    ->label('Tipo documento')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'modulo' => 'info',
                        'procedura' => 'warning',
                        'informativa' => 'info',
                        'template' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            // ==========================================
            // FILTRI DI RICERCA AVANZATI
            // ==========================================
            ->filters([
                // Filtro per Natura/Flusso
                SelectFilter::make('nature')
                    ->label('Natura Flusso')
                    ->options([
                        'incoming' => 'Da Ricevere',
                        'template_fillable' => 'Moduli da Compilare',
                        'compliance' => 'Compliance / Regolamenti',
                    ]),
                // Filtro rapido per i target di riferimento
                TernaryFilter::make('is_company')
                    ->label('Target Azienda')
                    ->placeholder('Tutti'),
                TernaryFilter::make('is_employee')
                    ->label('Target Dipendente')
                    ->placeholder('Tutti'),
                // Filtro per capire se richiede firma obbligatoria
                TernaryFilter::make('is_signed')
                    ->label('Richiede Firma')
                    ->placeholder('Tutti'),
            ])
            // ==========================================
            // AZIONI DI RIGA E DI GRUPPO
            // ==========================================
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\DocumentTypes\Tables;

use App\Models\DocumentType;
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
                TextColumn::make('source')
                    ->label('Come si ottiene')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        DocumentType::SOURCE_API => 'success',
                        DocumentType::SOURCE_STAMPA => 'warning',
                        DocumentType::SOURCE_TEMPLATE => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => DocumentType::SOURCES[$state] ?? (string) $state)
                    ->placeholder('Da indicare')
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
                TextColumn::make('client_scope')
                    ->label('Cliente')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => DocumentType::CLIENT_SCOPES[$state] ?? 'Entrambi')
                    ->placeholder('Entrambi')
                    ->toggleable(),
                TextColumn::make('signer_model')
                    ->label('Firma')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (?string $state, $record): string => $state.($record->approver_model ? ' → '.$record->approver_model : ''))
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_company')
                    ->label('Interno')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('source')
                    ->label('Come si ottiene')
                    ->options(DocumentType::SOURCES),
                SelectFilter::make('client_scope')
                    ->label('Cliente')
                    ->options(DocumentType::CLIENT_SCOPES),
                TernaryFilter::make('is_company')
                    ->label('Documento aziendale interno')
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

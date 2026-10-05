<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentStatus;
use App\Filament\Exports\DynamicGroupExport;
use App\Filament\Utils\TableHelper;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderableColumns()
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make(),
                    ])
                    ->label('Esporta Excel')
                    ->color('success'),
            ])
            ->columns([
                // 1. IDENTIFICATIVI PRINCIPALI

                // 2. ENTITÀ COLLEGATA (Rapporto Polimorfico reso leggibile)
                TableHelper::polymorphicColumn('documentable', 'Collegato'),

                // 3. TIPI DI DOCUMENTO
                TextColumn::make('name')
                    ->label('Nome Documento')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                // 4. DATE E SCADENZE
                // Sostituisci il vecchio TextColumn con questo:
                TextColumn::make('emitted_at')
                    ->label('Emissione')
                    ->date('d/m/y')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Scadenza')
                    ->date('d/m/y')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at && $record->expires_at->isPast() ? 'danger' : 'gray'),

                // 5. STATO E UTILITY
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approvato', 'attivo' => 'success',
                        'bozza' => 'gray',
                        'scaduto' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),

            ])
            ->filters([
                Filter::make('emitted_at')
                    ->label('Ha data emissione')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('emitted_at')),
                // FILTRO 1: Selezione per Tipo Entità (Polimorfica)

                SelectFilter::make('documentable_type')
                    ->label('Collegato a')
                    ->searchable()
                    ->preload()
                    ->options([
                        'fornitore' => 'Produttori',
                        'audit' => 'Audit',
                        'complaint' => 'Registri Reclami',

                        'cliente' => 'Clienti',
                        'company' => 'Aziende',

                        'employee' => 'Dipendenti',

                        'website' => 'Siti Web',
                    ])->default('fornitore'),

                // FILTRO 2: Selezione per Tipo Documento (Relazione)
                SelectFilter::make('document_type_id')
                    ->label('Tipo Documento')
                    ->relationship('documentType', 'name')
                    ->searchable()

                    ->preload(),

                // FILTRO 3: Selezione per Categoria (Doctype)
                SelectFilter::make('status')
                    ->label('Stati Documento')
                    ->multiple() // Abilita la selezione multipla
                    ->options(DocumentStatus::class) // Mappa automaticamente l'Enum
                    ->searchable() // Permette di cercare tra gli stati se la lista si allunga
                    ->preload(), // Carica subito le opzioni nel frontend per una risposta immediata

                // FILTRO 4: Selezione Intervallo di Scadenza
                Filter::make('expires_at')
                    ->label('Data Scadenza')
                    ->form([
                        DatePicker::make('expires_from')->label('Scadenza da'),
                        DatePicker::make('expires_until')->label('Scadenza a'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['expires_from'], fn ($q, $date) => $q->whereDate('expires_at', '>=', $date))
                            ->when($data['expires_until'], fn ($q, $date) => $q->whereDate('expires_at', '<=', $date));
                    }),

                TrashedFilter::make()
                    ->label('Eliminati'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setEmittedAt')
                        ->label('Imposta Data Emissione')
                        ->icon('heroicon-o-calendar')
                        ->color('success')
                        ->form([
                            DatePicker::make('emitted_at')
                                ->label('Data di Emissione')
                                ->required()
                                ->default(now()), // Imposta la data odierna come default
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $dataForced = $data['emitted_at'] ?? now(); // Usa la data fornita o la data odierna come fallback

                                $record->update([
                                    'emitted_at' => $dataForced,
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion() // Deseleziona i record dopo l'operazione
                        ->requiresConfirmation()
                        ->modalHeading('Imposta data di emissione per i record selezionati')
                        ->modalSubmitActionLabel('Salva'),
                    BulkAction::make('setExpiredAt')
                        ->label('Imposta Data Scadenza')
                        ->icon('heroicon-o-calendar')
                        ->color('success')
                        ->form([
                            DatePicker::make('expired_at')
                                ->label('Data di Scadenza')
                                ->required()
                                ->default(now()), // Imposta la data odierna come default
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'expired_at' => $data['expired_at'],
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion() // Deseleziona i record dopo l'operazione
                        ->requiresConfirmation()
                        ->modalHeading('Imposta data di emissione per i record selezionati')
                        ->modalSubmitActionLabel('Salva'),

                    // DeleteBulkAction::make(),
                    //  ForceDeleteBulkAction::make(),
                    //  RestoreBulkAction::make(),
                ]),
            ]);
    }
}

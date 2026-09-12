<?php

namespace App\Filament\Unicofin\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Str;



class ClientsTable
{
    

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Identificazione Rapida
                TextColumn::make('full_name')  // Presuppone un accessor nel modello o usa formatStateUsing
                    ->label('Cliente')
                    //   ->sortable()
                    ->description(fn($record) => $record->tax_code)
                    ->sortable()
                    ->searchable(['name', 'first_name', 'tax_code'])
                    ->state(fn($record) => $record->is_person
                        ? "{$record->name} {$record->first_name}"
                        : $record->name),
                // Stato Avanzamento (Badge colorati)
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'raccolta_dati' => 'gray',
                        'valutazione_aml' => 'warning',
                        'approvata' => 'success',
                        'sos_inviata' => 'danger',
                        'chiusa' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => str($state)->replace('_', ' ')->title()),
                // Indicatori di Rischio (Icone silenziose ma visibili)
                IconColumn::make('privacy_policy_read_at')
                    ->label('Privacy OK')
                    ->boolean()  // Trasforma il valore in boolean (null = false, date = true)
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->tooltip(fn($record) => $record->privacy_policy_read_at
                        ? 'Letta il: ' . $record->privacy_policy_read_at->format('d/m/Y H:i')
                        : 'Non ancora letta'),
                IconColumn::make('is_pep')
                    ->sortable()
                    ->label('PEP')
                    ->boolean()
                    ->trueIcon('heroicon-o-flag')
                    ->falseIcon('')  // Nasconde l'icona se falso per pulizia visiva
                    ->color('danger'),
                IconColumn::make('is_sanctioned')
                    ->label('Blacklist')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->color('danger'),
                IconColumn::make('is_art108')
                    ->label('Esente art. 108')
                    ->boolean()
                    ->trueIcon('heroicon-s-shield-check')
                    ->falseIcon('heroicon-o-x-mark')
                    ->color(fn($state) => $state ? 'success' : 'gray'),
                // Dati Finanziari
                TextColumn::make('salary')
                    ->sortable()
                    ->label('RAL')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Date
            ])
            ->filters([
                TernaryFilter::make('is_company')
                    ->label('Consulenti')
                    ->placeholder('Tutti')
                    ->default(false)
                    ->trueLabel('Consulenti')
                    ->falseLabel('Clienti'),
                // Filtro per tipologia
                TernaryFilter::make('is_person')
                    ->label('Tipo Soggetto')
                    ->placeholder('Tutti')
                    ->trueLabel('Persone Fisiche')
                    ->falseLabel('Persone Giuridiche'),
                // Filtro per Stato
                SelectFilter::make('status')
                    ->multiple()  // Permette di vedere più stati contemporaneamente
                    ->options([
                        'raccolta_dati' => 'Raccolta Dati',
                        'valutazione_aml' => 'In Valutazione',
                        'approvata' => 'Approvati',
                    ]),
                // Filtro Rischio
                Filter::make('high_risk')
                    ->label('Alto Rischio (AML)')
                    ->query(fn(Builder $query) => $query
                        ->where('is_pep', true)
                        ->orWhere('is_sanctioned', true)
                        ->orWhere('is_remote_interaction', true)),
            ])
            ->recordActions([
               
            ], position: RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                //  BulkActionGroup::make([
                //      DeleteBulkAction::make(),
                //  ]),
            ])
            ->defaultSort('name');
    }
}

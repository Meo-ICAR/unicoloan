<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\KycCoverage;
use App\Models\Client;
use App\Models\KycQuestionnaire;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->description(fn ($record) => $record->tax_code)
                    ->sortable()
                    ->searchable(['name', 'first_name', 'tax_code'])
                    ->state(fn ($record) => $record->is_person
                        ? "{$record->name} {$record->first_name}"
                        : $record->name),
                // Stato Avanzamento (Badge colorati)
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'raccolta_dati' => 'gray',
                        'valutazione_aml' => 'warning',
                        'approvata' => 'success',
                        'sos_inviata' => 'danger',
                        'chiusa' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),
                // Indicatori di Rischio (Icone silenziose ma visibili)
                IconColumn::make('privacy_policy_read_at')
                    ->label('Privacy OK')
                    ->boolean()  // Trasforma il valore in boolean (null = false, date = true)
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->tooltip(fn ($record) => $record->privacy_policy_read_at
                        ? 'Letta il: '.$record->privacy_policy_read_at->format('d/m/Y H:i')
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
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('kyc')
                    ->label('KYC')
                    ->badge()
                    ->state(fn (Client $record): KycCoverage => $record->kycCoverage())
                    ->formatStateUsing(fn (KycCoverage $state): string => $state->getLabel())
                    ->color(fn (KycCoverage $state): string => $state->getColor()),
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
                    ->label('Stato')
                    ->multiple()  // Permette di vedere più stati contemporaneamente
                    ->options([
                        'raccolta_dati' => 'Raccolta Dati',
                        'valutazione_aml' => 'In Valutazione',
                        'approvata' => 'Approvati',
                    ]),
                // Copertura KYC: gli id si calcolano in PHP perche' Client e KycQuestionnaire sono su connessioni diverse
                SelectFilter::make('kyc')
                    ->label('KYC')
                    ->options(KycCoverage::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        $coverage = KycQuestionnaire::coverageByClient();

                        if ($value === KycCoverage::Missing->value) {
                            return $query->whereNotIn('id', $coverage->keys()->all());
                        }

                        return $query->whereIn('id', $coverage
                            ->filter(fn (KycCoverage $item): bool => $item->value === $value)
                            ->keys()
                            ->all());
                    }),
                // Filtro Rischio
                Filter::make('high_risk')
                    ->label('Alto Rischio (AML)')
                    ->query(fn (Builder $query) => $query
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

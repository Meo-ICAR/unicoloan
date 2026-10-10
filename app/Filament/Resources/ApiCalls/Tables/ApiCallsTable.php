<?php

namespace App\Filament\Resources\ApiCalls\Tables;

use App\Enums\ApiCallStatus;
use App\Enums\ApiProvider;
use App\Models\ApiCall;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ApiCallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('provider')->label('Servizio')->badge()->sortable(),
                TextColumn::make('operation')->label('Operazione'),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (ApiCall $record): string => $record->subject_type === null
                        ? '-'
                        : class_basename($record->subject_type).' #'.$record->subject_id)
                    ->description(fn (ApiCall $record): ?string => $record->subject?->name ?? null),
                TextColumn::make('status')->label('Esito')->badge()->sortable(),
                TextColumn::make('summary')->label('Dettaglio')->wrap()->description(fn (ApiCall $record): ?string => $record->error),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Totale')->money('EUR')),
                TextColumn::make('duration_ms')->label('Durata (ms)')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')->label('Rif. provider')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_id')->label('Utente')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('response')
                    ->label('Risposta')
                    ->icon('heroicon-o-code-bracket')
                    ->visible(fn (ApiCall $record): bool => $record->response !== null)
                    ->modalHeading('Risposta del provider')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalContent(fn (ApiCall $record) => new HtmlString('<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode($record->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>')),
            ])
            ->filters([
                SelectFilter::make('provider')->label('Servizio')->options(ApiProvider::class),
                SelectFilter::make('status')->label('Esito')->options(ApiCallStatus::class),
            ]);
    }
}

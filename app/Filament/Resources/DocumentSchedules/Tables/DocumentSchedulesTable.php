<?php

namespace App\Filament\Resources\DocumentSchedules\Tables;

use Unico\Core\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSchedule;
use App\Services\DocumentReminderService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentSchedulesTable
{
    public const REASON_MISSING = 'assente';

    public const REASON_ANOMALY = 'anomalia';

    public const REASON_EXPIRING = 'scadenza';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('document'))
            ->defaultPaginationPageOption(50)
            ->defaultSort('expires_at')
            ->groups([
                Group::make('documentable_group_key')
                    ->label('Soggetto')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (DocumentSchedule $record): string => $record->entity_name)
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->state(fn (DocumentSchedule $record): string => self::reason($record))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        self::REASON_MISSING => 'Assente',
                        self::REASON_ANOMALY => 'Anomalia',
                        default => 'Scadenza',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        self::REASON_MISSING => 'info',
                        self::REASON_ANOMALY => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (DocumentSchedule $record): ?string => self::reason($record) === self::REASON_ANOMALY ? $record->document?->rejection_note : null),
                TextColumn::make('entity_name')->label('Soggetto')->searchable()->sortable(),
                TextColumn::make('document_name')->label('Documento')->searchable()->sortable(),
                TextColumn::make('document_type_name')->label('Tipo')->toggleable(),
                TextColumn::make('expires_at')
                    ->label('Scadenza')
                    ->date('d/m/y')
                    ->placeholder('-')
                    ->sortable()
                    ->color(fn (DocumentSchedule $record): string => $record->expires_at?->isPast() ? 'danger' : 'gray'),
                TextColumn::make('days_until_expiry')
                    ->label('Giorni')
                    ->badge()
                    ->state(fn (DocumentSchedule $record): ?int => $record->expires_at === null ? null : $record->days_until_expiry)
                    ->placeholder('-')
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 0 => 'danger',
                        $state <= 7 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('reminders_count')->label('Solleciti')->sortable(),
                TextColumn::make('last_sent_at')->label('Ultimo sollecito')->dateTime('d/m/Y H:i')->placeholder('Mai')->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options([self::REASON_MISSING => 'Assenti', self::REASON_ANOMALY => 'Con anomalia', self::REASON_EXPIRING => 'In scadenza'])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            self::REASON_MISSING => $query->whereHas('document', fn (Builder $document) => $document->where('status', DocumentStatus::PENDING->value)),
                            self::REASON_ANOMALY => $query->whereHas('document', fn (Builder $document) => $document->where('status', DocumentStatus::REJECTED->value)
                                ->orWhere(fn (Builder $uploaded) => $uploaded->where('status', DocumentStatus::UPLOADED->value)->whereJsonLength('metadata->verifica->anomalie', '>', 0))),
                            self::REASON_EXPIRING => $query->whereNotNull('expires_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('document_type_name')
                    ->label('Tipo documento')
                    ->options(fn (): array => DocumentSchedule::query()->distinct()->orderBy('document_type_name')->pluck('document_type_name', 'document_type_name')->all())
                    ->searchable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sollecita')
                        ->label('Invia solleciti')
                        ->icon('heroicon-o-paper-airplane')
                        ->requiresConfirmation()
                        ->modalDescription('Parte una sola email per ogni destinatario con il riepilogo dei documenti selezionati.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $service = app(DocumentReminderService::class);
                            $totals = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

                            foreach ($records->pluck('documentable_group_key')->unique() as $groupKey) {
                                $result = $service->sendReminders(false, $groupKey);
                                $totals['sent'] += $result['sent'];
                                $totals['skipped'] += $result['skipped'];
                                $totals['failed'] += $result['failed'];
                            }

                            Notification::make()->title("Solleciti: {$totals['sent']} inviati, {$totals['skipped']} saltati, {$totals['failed']} falliti")->success()->send();
                        }),
                ]),
            ]);
    }

    /**
     * Assente (richiesto e mai caricato), con anomalia (respinto o caricato con anomalie) oppure in scadenza.
     */
    public static function reason(DocumentSchedule $record): string
    {
        /** @var Document|null $document */
        $document = $record->document;

        return match (true) {
            $document?->status === DocumentStatus::REJECTED->value => self::REASON_ANOMALY,
            $document?->status === DocumentStatus::UPLOADED->value && count((array) ($document->metadata['verifica']['anomalie'] ?? [])) > 0 => self::REASON_ANOMALY,
            $document?->status === DocumentStatus::PENDING->value && $record->expires_at === null => self::REASON_MISSING,
            default => self::REASON_EXPIRING,
        };
    }
}

<?php

namespace App\Filament\Resources\DocumentVerifications\Tables;

use App\Enums\DocumentAnomaly;
use Unico\Core\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentVerifier;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DocumentVerificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['documentType', 'documentable', 'uploader']))
            ->columns([
                TextColumn::make('name')
                    ->label('Documento')
                    ->searchable()
                    ->url(fn (Document $record): ?string => $record->getFirstMedia('documents') || $record->getFirstMedia('signed') ? route('documents.download', $record) : null, shouldOpenInNewTab: true),
                TextColumn::make('documentType.name')->label('Tipo')->searchable(),
                TextColumn::make('documentable')
                    ->label('Record')
                    ->state(fn (Document $record): string => class_basename((string) $record->documentable_type).': '.($record->documentable?->codice_pratica ?? trim(($record->documentable?->name ?? '').' '.($record->documentable?->first_name ?? '')) ?: $record->documentable_id)),
                TextColumn::make('firma')
                    ->label('Firma')
                    ->badge()
                    ->state(fn (Document $record): string => match (self::check($record)['signature'] ?? DocumentVerifier::SIGNATURE_NOT_REQUIRED) {
                        DocumentVerifier::SIGNATURE_OTP => 'OTP',
                        DocumentVerifier::SIGNATURE_OTP_PENDING => 'OTP in attesa',
                        DocumentVerifier::SIGNATURE_DIGITAL => 'Digitale',
                        DocumentVerifier::SIGNATURE_HANDWRITTEN => 'Olografa da verificare',
                        DocumentVerifier::SIGNATURE_MISSING => 'Assente',
                        default => '-',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'OTP', 'Digitale' => 'success',
                        'Olografa da verificare', 'OTP in attesa' => 'warning',
                        'Assente' => 'danger',
                        default => 'gray',
                    })
                    ->description(function (Document $record): ?string {
                        $otp = self::check($record)['otp'] ?? null;

                        return $otp === null ? null : ucfirst($otp['provider']).' '.($otp['provider_ref'] ?? '').($otp['signed_at'] ? ' · '.Carbon::parse($otp['signed_at'])->format('d/m/Y H:i') : '');
                    }),
                TextColumn::make('anomalie')
                    ->label('Anomalie')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Document $record): array => collect(self::check($record)['anomalie'] ?? [])->map(fn (string $code): string => DocumentAnomaly::tryFrom($code)?->getLabel() ?? $code)->all())
                    ->placeholder('Nessuna'),
                TextColumn::make('created_at')->label('Caricato il')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('uploader.name')->label('Caricato da')->toggleable(),
            ])
            ->filters([
                Filter::make('con_anomalie')
                    ->label('Solo con anomalie o firma da verificare')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->whereJsonLength('metadata->verifica->anomalie', '>', 0)
                        ->orWhere('metadata->verifica->signature', DocumentVerifier::SIGNATURE_HANDWRITTEN))),
                SelectFilter::make('document_type_id')->label('Tipo')->relationship('documentType', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('transazione')
                    ->label('Transazione firma')
                    ->icon('heroicon-o-finger-print')
                    ->color('gray')
                    ->visible(fn (Document $record): bool => isset(self::check($record)['otp']))
                    ->modalHeading('Transazione di firma')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalContent(function (Document $record): HtmlString {
                        $otp = self::check($record)['otp'];
                        $rows = collect($otp['signers'])->map(fn (array $signer): string => '<tr><td>'.e($signer['name']).'</td><td>'.e($signer['role']).'</td><td>'.e($signer['status']).'</td><td>'.e($signer['signed_at'] ? Carbon::parse($signer['signed_at'])->format('d/m/Y H:i') : '-').'</td></tr>')->implode('');

                        return new HtmlString('<p><strong>'.e(ucfirst($otp['provider'])).'</strong> · rif. <code>'.e($otp['provider_ref'] ?? '-').'</code> · stato '.e($otp['status']).'</p><table style="width:100%;margin-top:8px"><thead><tr><th align="left">Firmatario</th><th align="left">Ruolo</th><th align="left">Stato</th><th align="left">Firmato il</th></tr></thead><tbody>'.$rows.'</tbody></table>');
                    }),
                Action::make('accetta')
                    ->label('Accetta')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Accettare il documento?')
                    ->schema(fn (Document $record): array => (self::check($record)['signature'] ?? null) === DocumentVerifier::SIGNATURE_HANDWRITTEN
                        ? [Checkbox::make('firma_verificata')->label('Confermo di aver verificato la presenza della firma olografa')->accepted()]
                        : [])
                    ->action(function (Document $record): void {
                        $record->forceFill([
                            'status' => DocumentStatus::APPROVED->value,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'rejection_note' => null,
                        ])->save();

                        Notification::make()->title('Documento accettato')->success()->send();
                    }),
                Action::make('rifiuta')
                    ->label('Rifiuta')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Rifiutare il documento')
                    ->schema([
                        Select::make('anomalia')->label('Anomalia')->options(DocumentAnomaly::class)->required(),
                        Textarea::make('nota')->label('Nota per chi ha caricato il documento')->rows(3),
                    ])
                    ->action(function (array $data, Document $record): void {
                        $anomaly = $data['anomalia'] instanceof DocumentAnomaly ? $data['anomalia'] : DocumentAnomaly::from($data['anomalia']);

                        $record->forceFill([
                            'status' => DocumentStatus::REJECTED->value,
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                            'rejection_note' => trim($anomaly->getLabel().(filled($data['nota'] ?? null) ? ': '.$data['nota'] : '')),
                            'metadata' => array_merge((array) $record->metadata, ['verifica' => array_merge((array) ($record->metadata['verifica'] ?? []), ['rifiuto' => $anomaly->value])]),
                        ])->save();

                        Notification::make()->title('Documento rifiutato')->body($record->rejection_note)->warning()->send();
                    }),
            ]);
    }

    /**
     * @return array{signature?: string, anomalie?: array<int, string>}
     */
    private static function check(Document $record): array
    {
        return (array) ($record->metadata['verifica'] ?? []);
    }
}

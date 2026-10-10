<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\Client;
use App\Models\PROFORMA\Clienti;
use App\Models\Tipoprodotto;
use App\Services\MandatoPraticaCreator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Dal cliente: crea il mandato e la prima pratica di finanziamento.
 */
class CreaMandatoPraticaAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'creaMandatoPratica';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Mandato e pratica')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->modalHeading('Crea mandato e prima pratica')
            ->modalDescription('Viene creato un mandato attivo (2 anni, se il prodotto lo richiede) e una pratica in stato INSERITA collegata al cliente.')
            ->modalSubmitActionLabel('Crea')
            ->disabled(fn (Client $record): bool => blank($record->tax_code))
            ->tooltip(fn (Client $record): ?string => blank($record->tax_code) ? 'Il cliente non ha codice fiscale / P.IVA' : null)
            ->schema([
                Select::make('tipo_prodotto')
                    ->label('Tipo prodotto')
                    ->options(fn (Client $record): array => Tipoprodotto::query()
                        ->whereNotNull('name')->where('name', '!=', '')
                        ->where('is_third_party', false)
                        ->where($record->is_person ? 'for_person' : 'for_company', true)
                        ->orderBy('name')->pluck('name', 'name')->all())
                    ->helperText('Sono elencati i prodotti adatti al tipo di cliente (persona fisica o giuridica).')
                    ->searchable()
                    ->required(),
                Select::make('denominazione_banca')
                    ->label('Istituto')
                    ->options(fn (): array => Clienti::query()->whereNotNull('name')->orderBy('name')->pluck('name', 'name')->all())
                    ->searchable(),
                TextInput::make('amount')
                    ->label('Importo richiesto')
                    ->numeric()
                    ->prefix('€')
                    ->required(),
                TextInput::make('scopo_finanziamento')
                    ->label('Scopo del finanziamento')
                    ->maxLength(255),
            ])
            ->action(function (array $data, Client $record): void {
                $created = app(MandatoPraticaCreator::class)->create($record, $data);

                Notification::make()
                    ->title($created['mandate'] === null
                        ? 'Pratica '.$created['pratica']->codice_pratica.' creata (prodotto senza mandato)'
                        : 'Mandato '.$created['mandate']->numero_mandato.' e pratica '.$created['pratica']->codice_pratica.' creati')
                    ->success()
                    ->actions([
                        Action::make('apri')->label('Apri la pratica')->url(PraticaResource::getUrl('edit', ['record' => $created['pratica']->getKey()])),
                    ])
                    ->send();
            });
    }
}

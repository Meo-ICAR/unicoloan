<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Impegni di terzi del cliente (finanziamenti in corso presso altri istituti): sono righe di
 * `pratiches` con `is_notowned`, ma non sono pratiche di lavorazione.
 */
class ImpegniTerziRelationManager extends RelationManager
{
    protected static string $relationship = 'thirdPartyFinancings';

    protected static ?string $title = 'Impegni di terzi';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codice_pratica')
            ->headerActions([
                Action::make('aggiungiImpegno')
                    ->label('Aggiungi impegno')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Aggiungi impegno di terzi')
                    ->modalSubmitActionLabel('Aggiungi')
                    ->schema(fn (): array => [
                        Select::make('tipo_prodotto')
                            ->label('Tipo prodotto')
                            ->options(fn (): array => Tipoprodotto::query()
                                ->whereNotNull('name')->where('name', '!=', '')
                                ->where($this->getOwnerRecord()->is_person ? 'for_person' : 'for_company', true)
                                ->orderBy('name')->pluck('name', 'name')->all())
                            ->searchable(),
                        TextInput::make('denominazione_banca')->label('Banca / ente')->maxLength(255),
                        TextInput::make('denominazione_prodotto')->label('Descrizione')->maxLength(255),
                        TextInput::make('rata')->label('Rata')->numeric()->prefix('€'),
                        TextInput::make('nrate')->label('Numero rate')->numeric()->integer(),
                        TextInput::make('amount')->label('Importo')->numeric()->prefix('€'),
                        DatePicker::make('data_inserimento_pratica')->label('Data')->default(today()),
                    ])
                    ->action(function (array $data): void {
                        $client = $this->getOwnerRecord();

                        Pratica::create([
                            'id' => (string) Str::uuid(),
                            'codice_pratica' => 'TERZI-'.Str::upper(Str::random(8)),
                            'nome_cliente' => $client->is_person ? $client->first_name : null,
                            'cognome_cliente' => $client->name,
                            'codice_fiscale' => $client->tax_code,
                            'tipo_prodotto' => $data['tipo_prodotto'] ?? null,
                            'denominazione_banca' => $data['denominazione_banca'] ?? null,
                            'denominazione_prodotto' => $data['denominazione_prodotto'] ?? null,
                            'rata' => $data['rata'] ?? null,
                            'nrate' => $data['nrate'] ?? null,
                            'amount' => $data['amount'] ?? null,
                            'data_inserimento_pratica' => $data['data_inserimento_pratica'] ?? today(),
                            'stato_pratica' => 'PERFEZIONATA',
                            'is_notowned' => true,
                        ]);

                        Notification::make()->title('Impegno aggiunto')->success()->send();
                    })
                    ->disabled(fn (): bool => blank($this->getOwnerRecord()->tax_code))
                    ->tooltip(fn (): ?string => blank($this->getOwnerRecord()->tax_code) ? 'Il cliente non ha codice fiscale / P.IVA' : null),
            ])
            ->columns([
                TextColumn::make('tipo_prodotto')->label('Tipo')->badge(),
                TextColumn::make('denominazione_banca')->label('Banca')->searchable(),
                TextColumn::make('denominazione_prodotto')->label('Prodotto'),
                TextColumn::make('rata')->label('Rata')->money('EUR'),
                TextColumn::make('nrate')->label('Numero rate'),
                TextColumn::make('amount')->label('Importo')->money('EUR'),
                TextColumn::make('data_inserimento_pratica')->label('Data')->date(),
            ]);
    }
}

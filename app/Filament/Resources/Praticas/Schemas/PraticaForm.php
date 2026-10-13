<?php

namespace App\Filament\Resources\Praticas\Schemas;

use App\Enums\FinancingSection;
use App\Enums\FinancingType;
use App\Models\PraticaStati;
use App\Models\Tipoprodotto;
use App\Models\TipoprodottoSub;
use App\Services\BlacklistChecker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Unico\Core\Models\TipoProdotto as CoreTipoProdotto;

class PraticaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // 1. INFORMAZIONI GENERALI
                Section::make('Informazioni Generali e Stato')
                    ->description('Dati identificativi della pratica')
                    ->columns(['sm' => 1, 'md' => 2, 'lg' => 4]) // Layout responsivo
                    ->schema([
                        TextInput::make('codice_pratica')
                            ->label('Codice Pratica')
                            ->maxLength(255)
                            ->required()
                            ->columnSpan(1),

                        Select::make('stato_pratica')
                            ->label('Stato Pratica')
                            ->options(PraticaStati::pluck('stato_pratica', 'stato_pratica'))
                            ->searchable()
                            ->required()
                            ->columnSpan(['sm' => 1, 'md' => 2]), // Dà il doppio dello spazio per evitare che il testo vada a capo

                        Textarea::make('status_note')
                            ->label('Annotazione sul cambio stato')
                            ->helperText('Viene registrata nello storico con data e utente.')
                            ->rows(2)
                            ->visibleOn('edit')
                            ->dehydrated()
                            ->columnSpanFull(),

                    ]),

                // 3. AGENTE E ISTITUTO BANCARIO
                Section::make('Dati Rete e Istituto')
                    ->columns(['sm' => 1, 'md' => 2])
                    ->schema([

                        TextInput::make('nome_cliente')
                            ->label('Nome')
                            ->maxLength(191)
                            ->required()
                            ->visible(fn (Get $get): bool => FinancingSection::PraticaNomeCliente->isVisibleFor(self::financingType($get))),

                        TextInput::make('cognome_cliente')
                            ->label(fn (Get $get): string => self::financingType($get) === FinancingType::Impresa ? 'Ragione Sociale' : 'Cognome')
                            ->maxLength(191)
                            ->required(),
                        TextInput::make('denominazione_banca')
                            ->label('Banca Erogatrice')
                            ->maxLength(191),
                        //  ->columnSpan('full'), // Dà alla banca l'intera riga per i nomi lunghi
                        TextInput::make('denominazione_agente')
                            ->label('Agente / Rappresentante')
                            ->live(onBlur: true)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    if (app(BlacklistChecker::class)->isAgenteNameBlacklistedForBancaName($value, $get('denominazione_banca'))) {
                                        $fail('Questo agente è in blacklist per la banca selezionata e non può essere assegnato a questa pratica.');
                                    }
                                },
                            ]),
                        //  ->columnSpan('full'),

                    ]),

                // 4. DATI PRODOTTO E FINANZIARI
                Section::make('Dati Prodotto e Importi')
                    ->columns(['sm' => 1, 'md' => 2])
                    ->schema([
                        Toggle::make('is_notowned')
                            ->label('Impegno di terzi')
                            ->inline(false)
                            ->default(false)
                            ->live(),

                        Select::make('tipo_prodotto')
                            ->options(fn (): array => Tipoprodotto::whereNotNull('tipo_prodotto')->pluck('name', 'tipo_prodotto')
                                ->union(CoreTipoProdotto::query()->whereNotNull('tipo_prodotto')->pluck('name', 'tipo_prodotto'))
                                ->all())
                            ->label('Macro Prodotto')
                            ->live()
                            ->helperText(fn (Get $get): string => 'Tipo finanziamento: '.(self::financingType($get)?->getLabel() ?? 'non riconosciuto, sono mostrati tutti i campi'))
                            ->visible(fn (Get $get): bool => ! $get('is_notowned')),

                        Select::make('denominazione_prodotto')
                            ->options(TipoprodottoSub::pluck('name', 'name'))
                            ->label('Prodotto')
                            ->live()
                            ->visible(fn (Get $get): bool => ! $get('is_notowned')),
                        TextInput::make('erogato')
                            ->label('Erogato')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('€')
                            ->visible(fn (Get $get): bool => ! $get('is_notowned')),

                        Fieldset::make('Dettagli Economici')
                            ->columns(['sm' => 1, 'md' => 3])
                            ->columnSpan('full')
                            ->schema([
                                TextInput::make('rata')
                                    ->label('Rata Mensile')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => $get('is_notowned') || FinancingSection::PraticaRateizzazione->isVisibleFor(self::financingType($get))),

                                TextInput::make('nrate')
                                    ->label('N. Rate')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(480)
                                    ->visible(fn (Get $get): bool => $get('is_notowned') || FinancingSection::PraticaRateizzazione->isVisibleFor(self::financingType($get))),
                                DatePicker::make('data_inserimento_pratica')
                                    ->label(fn (Get $get): string => $get('is_notowned') ? 'Data decorrenza' : 'Inserimento')
                                    ->default(now()),
                                TextInput::make('amount')
                                    ->label('Importo Lordo')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => ! $get('is_notowned')),

                                TextInput::make('net')
                                    ->label('Importo Netto')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => ! $get('is_notowned')),

                            ])->columns(fn (Get $get): int => $get('is_notowned') ? 3 : 4),

                        Textarea::make('descrizione_immobile')
                            ->label('Descrizione immobile')
                            ->helperText('Indirizzo, tipologia e dati catastali dell\'immobile oggetto del mutuo.')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => ! $get('is_notowned') && FinancingSection::PraticaDatiImmobile->isVisibleFor(self::financingType($get))),
                    ])->columnSpan('full'),

                // 5. TIMELINE OPERATIVA (solo pratiche proprie: per impegni di terzi basta la data decorrenza sopra)
                Section::make('Timeline Operativa')
                    ->description('Date di avanzamento della pratica')
                    ->columns(['sm' => 1, 'md' => 2, 'lg' => 4])
                    ->visible(fn (Get $get): bool => ! $get('is_notowned'))
                    ->schema([
                        DatePicker::make('sended_at')
                            ->label('Invio a Banca'),

                        DatePicker::make('approved_at')
                            ->label('Approvazione'),

                        DatePicker::make('erogated_at')
                            ->label('Erogazione'),

                        DatePicker::make('rejected_at')
                            ->label('Rifiuto'),
                    ])->columnSpan('full'),

                // 6. STORICO STATI E ANNOTAZIONI
                Section::make('Storico stati e annotazioni')
                    ->description('Cambi di stato e annotazioni degli istruttori')
                    ->collapsible()
                    ->collapsed()
                    ->visibleOn('edit')
                    ->columnSpan('full')
                    ->schema([
                        RepeatableEntry::make('statusHistory')
                            ->hiddenLabel()
                            ->placeholder('Nessun cambio di stato registrato.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('changed_at')->label('Data')->dateTime('d/m/Y H:i'),
                                TextEntry::make('status_to')
                                    ->label('Stato')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state, $record): string => $record->status_from !== null && $record->status_from !== $state ? $record->status_from.' → '.$state : $state),
                                TextEntry::make('user.name')->label('Utente')->placeholder('Sistema'),
                                TextEntry::make('notes')->label('Annotazioni')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    private static function financingType(Get $get): ?FinancingType
    {
        return FinancingType::fromProduct($get('tipo_prodotto')) ?? FinancingType::fromProduct($get('denominazione_prodotto'));
    }
}

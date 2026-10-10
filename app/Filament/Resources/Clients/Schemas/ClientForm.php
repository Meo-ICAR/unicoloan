<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use App\Services\AmlScreeningService;
use App\Services\CervedService;
use App\Services\CodiceFiscaleDecoder;
use App\Services\CompanyShareholderImporter;
use App\Services\OpenApiCompanyService;
use App\Services\PartitaIva;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informazioni Cliente')
                    ->tabs([
                        // --- TAB 1: ANAGRAFICA ---
                        Tab::make('Anagrafica')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Dati Identificativi')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(fn (Get $get) => $get('is_person') ? 'Cognome / Ragione Sociale' : 'Ragione Sociale')
                                            ->required()
                                            ->maxLength(255),
                                        Toggle::make('is_person')
                                            ->label('Persona Fisica')
                                            ->default(true)
                                            ->live(),  // Ricarica la form al cambio
                                        TextInput::make('first_name')
                                            ->label('Nome')
                                            ->visible(fn (Get $get) => $get('is_person'))  // Scompare se azienda
                                            ->maxLength(255),
                                        TextInput::make('tax_code')
                                            ->label(fn (Get $get) => $get('is_person') ? 'Codice Fiscale' : 'P.IVA')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(16)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                if (! $get('is_person')) {
                                                    if (filled($state) && ! app(PartitaIva::class)->isValid($state)) {
                                                        Notification::make()->title('Partita IVA non valida')->body('Formato o cifra di controllo errati.')->warning()->send();
                                                    }

                                                    return;
                                                }

                                                if (strlen(trim((string) $state)) !== 16) {
                                                    return;
                                                }

                                                $decoded = app(CodiceFiscaleDecoder::class)->decodeVerified($state);

                                                if ($decoded === null) {
                                                    Notification::make()->title('Codice fiscale non valido')->body('Il carattere di controllo non è corretto: dati anagrafici non compilati.')->warning()->send();

                                                    return;
                                                }

                                                $set('birth_date', $decoded['birth_date']->format('Y-m-d'));
                                                $set('sex', $decoded['sex']);
                                                $set('birth_place', $decoded['birth_place']);
                                            })
                                            ->suffixActions([

                                                Action::make('cerved')
                                                    ->label('Cerved')
                                                    ->tooltip('Interroga Cerved')
                                                    ->icon('heroicon-m-magnifying-glass')
                                                    ->mountUsing(fn (Action $action, Get $get, ?Client $record) => self::ensureSaved($action, $get, $record, ['tax_code']))
                                                    ->requiresConfirmation()
                                                    ->modalHeading('Interrogare Cerved?')
                                                    ->modalDescription('La consultazione di Cerved è a pagamento. Confermi il lancio?')
                                                    ->modalSubmitActionLabel('Interroga')
                                                    ->action(function (Get $get, ?Client $record): void {
                                                        $taxCode = (string) $get('tax_code');

                                                        if (blank($taxCode)) {
                                                            Notification::make()->title('Inserire prima codice fiscale / P.IVA')->warning()->send();

                                                            return;
                                                        }

                                                        try {
                                                            $score = app(CervedService::class)->fetchScore($taxCode, $record);
                                                        } catch (\Throwable $e) {
                                                            Notification::make()->title('Cerved')->body($e->getMessage())->danger()->send();

                                                            return;
                                                        }

                                                        Notification::make()
                                                            ->title('Cerved: '.($score['denominazione'] ?? $taxCode))
                                                            ->body(collect([
                                                                $score['descrizione_score'],
                                                                'Valore: '.$score['valore'],
                                                                'Categoria: '.$score['categoria_codice'].' - '.$score['categoria_descrizione'],
                                                            ])->filter()->implode(' · '))
                                                            ->success()
                                                            ->persistent()
                                                            ->send();
                                                    }),
                                                self::openApiAction(),
                                            ]),
                                    ])
                                    ->columns(4),
                                Section::make('Contatti & Origine')
                                    ->schema([
                                        TextInput::make('email')->email()->label('Email'),
                                        TextInput::make('phone')->label('Telefono')->tel(),
                                        Select::make('client_type_id')
                                            ->label('Tipologia')
                                            ->relationship('clientType', 'name')
                                            ->searchable(),
                                    ])
                                    ->columns(3),
                                Section::make('Dati Anagrafici')
                                    ->schema([
                                        DatePicker::make('birth_date')->label('Data di nascita'),
                                        TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(100),
                                        Select::make('sex')->label('Sesso')->options(['M' => 'M', 'F' => 'F']),
                                        TextInput::make('citizenship')->label('Cittadinanza')->maxLength(60),
                                        Select::make('legal_representative_id')
                                            ->label('Legale rappresentante / Amministratore')
                                            ->relationship(
                                                'legalRepresentative',
                                                'name',
                                                fn (Builder $query) => $query->where('is_person', true),
                                            )
                                            ->searchable()
                                            ->visible(fn (Get $get) => ! $get('is_person')),
                                    ])
                                    ->columns(4),
                                Section::make('Dati Societari')
                                    ->schema([
                                        TextInput::make('pec')->label('PEC')->email()->maxLength(255),
                                        TextInput::make('ateco_code')->label('Codice Ateco')->maxLength(10),
                                        TextInput::make('cciaa_registration')->label('Iscrizione CCIAA')->maxLength(60),
                                        TextInput::make('legal_form')->label('Forma giuridica')->maxLength(100),
                                        TextInput::make('sdi_code')->label('Codice SDI')->maxLength(10),
                                        TextInput::make('activity_status')->label('Stato attività')->maxLength(30),
                                        DatePicker::make('company_started_at')->label('Inizio attività'),
                                    ])
                                    ->columns(3)
                                    ->visible(fn (Get $get) => ! $get('is_person')),
                                Section::make('Ultimo bilancio (registro imprese)')
                                    ->schema([
                                        TextInput::make('balance_year')->label('Anno')->numeric(),
                                        TextInput::make('share_capital')->label('Capitale sociale')->numeric()->prefix('€'),
                                        TextInput::make('turnover')->label('Fatturato')->numeric()->prefix('€'),
                                        TextInput::make('net_worth')->label('Patrimonio netto')->numeric()->prefix('€'),
                                        TextInput::make('employees')->label('Dipendenti')->numeric(),
                                        DateTimePicker::make('registry_updated_at')->label('Dati aggiornati il')->disabled()->dehydrated(false),
                                    ])
                                    ->columns(3)
                                    ->visible(fn (Get $get) => ! $get('is_person')),
                                Section::make('Dati Bancari e Lavorativi')
                                    ->schema([
                                        TextInput::make('iban')
                                            ->label('IBAN')
                                            ->maxLength(34)
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                                ? strtoupper(str_replace(' ', '', $state))
                                                : null),
                                        Select::make('employer_id')
                                            ->label('Datore di lavoro')
                                            ->relationship(
                                                'employer',
                                                'name',
                                                fn (Builder $query) => $query->where('is_person', false),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Get $get) => (bool) $get('is_person')),
                                    ])
                                    ->columns(2),
                            ]),
                        // --- TAB 2: COMPLIANCE & PRIVACY ---
                        Tab::make('Compliance AML')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Valutazione Rischio (AML)')
                                    ->key('valutazioneAml')
                                    ->description('Indicatori di rischio e posizioni critiche')
                                    ->headerActions([
                                        Action::make('verificaPep')
                                            ->label('Verifica PEP')
                                            ->icon('heroicon-m-shield-exclamation')
                                            ->mountUsing(fn (Action $action, Get $get, ?Client $record) => self::ensureSaved($action, $get, $record, ['name', 'first_name', 'birth_date', 'is_person']))
                                            ->requiresConfirmation()
                                            ->modalHeading('Verificare le liste PEP?')
                                            ->modalDescription('Il nominativo del cliente viene inviato al servizio sanctions.io. Confermi?')
                                            ->modalSubmitActionLabel('Verifica')
                                            ->action(function (Get $get, ?Client $record): void {
                                                $isPerson = (bool) $get('is_person');
                                                $name = trim(($isPerson ? ($get('first_name').' ') : '').$get('name'));

                                                if (blank($name)) {
                                                    Notification::make()->title('Inserire prima il nominativo del cliente')->warning()->send();

                                                    return;
                                                }

                                                try {
                                                    $matches = app(AmlScreeningService::class)->searchPep($name, $isPerson, $isPerson ? $get('birth_date') : null, $record);
                                                } catch (\Throwable $e) {
                                                    Notification::make()->title('Verifica PEP')->body($e->getMessage())->danger()->send();

                                                    return;
                                                }

                                                if ($matches === []) {
                                                    Notification::make()->title('Verifica PEP: nessuna corrispondenza')->body($name)->success()->send();

                                                    return;
                                                }

                                                Notification::make()
                                                    ->title('Verifica PEP: '.count($matches).' possibili corrispondenze')
                                                    ->body(collect($matches)->take(5)->map(fn (array $match): string => $match['name'].' ('.number_format($match['score'] * 100).'%)'.($match['countries'] !== [] ? ' - '.implode(', ', $match['countries']) : ''))->implode(' · ').'. Verificare a mano prima di spuntare PEP.')
                                                    ->warning()
                                                    ->persistent()
                                                    ->send();
                                            }),
                                    ])
                                    ->schema([
                                        Toggle::make('is_pep')->label('PEP (Esposto Politicamente)'),
                                        Toggle::make('is_sanctioned')->label('Sanzionato / Blacklist'),
                                        Toggle::make('is_art108')
                                            ->label('Esente art. 108 - ex art. 128-novies TUB')
                                            ->helperText("Seleziona se il cliente è esente ai sensi dell'art. 108 del Testo Unico Bancario"),
                                        Toggle::make('is_remote_interaction')->label('Interazione a Distanza'),
                                        Select::make('status')
                                            ->label('Stato verifica cliente')
                                            ->options([
                                                'raccolta_dati' => 'Raccolta Dati',
                                                'valutazione_aml' => 'Valutazione AML',
                                                'approvata' => 'Approvata',
                                                'sos_inviata' => 'SOS Inviata',
                                                'chiusa' => 'Chiusa',
                                            ]),
                                        Toggle::make('is_approved')->label('Approvato'),
                                        DateTimePicker::make('blacklist_at')
                                            ->label('Data Blacklist')
                                            ->readOnly(),
                                    ])
                                    ->columns(3),
                                Section::make('Consensi Privacy')
                                    ->schema([
                                        DateTimePicker::make('general_consent_at')->label('Consenso Base'),
                                        DateTimePicker::make('consent_marketing_at')->label('Marketing'),
                                        DateTimePicker::make('consent_profiling_at')->label('Profilazione'),
                                        DateTimePicker::make('consent_sic_at')->label('Consenso SIC (CRIF)'),
                                        Textarea::make('subfornitori')
                                            ->label('Subfornitori che trattano dati personali per conto del cliente')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Section::make('Amministrazione / Stato')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Toggle::make('is_anonymous')->label('Anagrafica di comodo non reale'),
                                            Toggle::make('is_lead')->label('È un Lead'),
                                            Select::make('leadsource_id')
                                                ->relationship('leadSource', 'name')
                                                ->label('Sorgente Lead')
                                                ->searchable(),
                                        ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Lente Openapi per le societa': dati societari nel form e soci importati come Client legati in client_relations.
     */
    private static function openApiAction(): Action
    {
        return Action::make('openapi')
            ->label('Openapi')
            ->tooltip('Dati societari e soci da Openapi')
            ->icon('heroicon-m-building-office-2')
            ->visible(fn (Get $get): bool => ! $get('is_person'))
            ->mountUsing(fn (Action $action, Get $get, ?Client $record) => self::ensureSaved($action, $get, $record, ['tax_code']))
            ->requiresConfirmation()
            ->modalHeading('Interrogare Openapi?')
            ->modalDescription('La consultazione di Openapi è a pagamento (dati societari e, se non inclusi, elenco soci). Confermi il lancio?')
            ->modalSubmitActionLabel('Interroga')
            ->action(function (Get $get, Set $set, Client $record): void {
                $vat = (string) $get('tax_code');

                if (! app(PartitaIva::class)->isValid($vat)) {
                    Notification::make()->title('Inserire una partita IVA valida')->warning()->send();

                    return;
                }

                try {
                    $result = app(OpenApiCompanyService::class)->fetchCompany($vat, $record);
                } catch (\Throwable $e) {
                    Notification::make()->title('Openapi')->body($e->getMessage())->danger()->send();

                    return;
                }

                $company = $result['company'];

                foreach (['name' => 'name', 'pec' => 'pec', 'ateco' => 'ateco_code', 'cciaa' => 'cciaa_registration', 'legal_form' => 'legal_form', 'sdi_code' => 'sdi_code', 'activity_status' => 'activity_status', 'started_at' => 'company_started_at'] as $source => $field) {
                    if (filled($company[$source])) {
                        $set($field, $company[$source]);
                    }
                }

                foreach (['year' => 'balance_year', 'employees' => 'employees', 'turnover' => 'turnover', 'net_worth' => 'net_worth', 'share_capital' => 'share_capital'] as $source => $field) {
                    if (filled($company['balance'][$source])) {
                        $set($field, $company['balance'][$source]);
                    }
                }

                $shareholders = $result['shareholders'];
                $body = collect($shareholders)->map(fn (array $holder): string => trim($holder['first_name'].' '.$holder['name']).' ('.($holder['percent'] ?? '?').'%)')->implode(' · ');

                $importer = app(CompanyShareholderImporter::class);
                $outcome = $importer->import($record, $shareholders);
                $importer->syncRegisteredOffice($record, $company['office']);
                $importer->syncRegistryData($record, $company);

                Notification::make()
                    ->title('Openapi: '.($company['name'] ?? $vat))
                    ->body($shareholders === [] ? 'Dati societari compilati. Nessun socio con quota >= 10%.' : "Soci: {$body}. Nuovi clienti: {$outcome['created']}, nuovi legami: {$outcome['linked']}.")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Le chiamate ai web service (a pagamento e registrate sul record) partono solo da un cliente salvato
     * e senza modifiche pendenti sui campi che vengono inviati: altrimenti l'azione si annulla con un avviso.
     *
     * @param  array<int, string>  $fields
     */
    private static function ensureSaved(Action $action, Get $get, ?Client $record, array $fields): void
    {
        if ($record === null) {
            Notification::make()->title('Salva prima il cliente')->body('Le interrogazioni ai servizi esterni si lanciano su un cliente già salvato.')->warning()->send();

            $action->cancel();
        }

        $normalize = fn (mixed $value): string => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : trim((string) ($value ?? ''));

        foreach ($fields as $field) {
            if ($normalize($get($field)) !== $normalize($record->getAttribute($field))) {
                Notification::make()->title('Salva le modifiche prima di interrogare')->body('I dati del cliente sono stati modificati e non ancora salvati.')->warning()->send();

                $action->cancel();
            }
        }
    }
}

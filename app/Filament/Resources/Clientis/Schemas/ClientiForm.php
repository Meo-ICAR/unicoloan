<?php

namespace App\Filament\Resources\Clientis\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ClientiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Dettagli')
                    ->tabs([
                        // TAB 1: ANAGRAFICA GENERALE
                        Tabs\Tab::make('Anagrafica')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('nome')
                                        ->maxLength(255)
                                        ->label('Denominazione in fatturazione'),
                                    Select::make('principal_type')
                                        ->options([
                                            'banca' => 'Banca',
                                            'broker' => 'Broker',
                                            'captive' => 'Broker Captive',
                                            'assicurazione' => 'Assicurazione',
                                            '--' => '--',
                                        ])
                                        ->label('Tipo Convenzionato'),
                                    TextInput::make('piva')
                                        ->maxLength(16)
                                        ->label('Partita IVA'),
                                ]),
                                Grid::make(3)->schema([]),
                                Section::make('Stato Operativo')
                                    ->compact()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255)
                                                ->label('Dizione in istruttoria'),
                                            Toggle::make('is_active')
                                                ->default(true)
                                                ->label('Cliente Attivo operativo'),
                                            Toggle::make('is_dummy')
                                                ->default(false)
                                                ->label('Record di Test (Dummy)'),
                                        ]),
                                    ]),
                                Section::make('Associazione prodotti')
                                    ->description('Seleziona i prodotti convenzionati con questa mandante.')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        CheckboxList::make('oamCodes')
                                            ->label('Prodotti')
                                            ->relationship(
                                                name: 'oamCodes',
                                                titleAttribute: 'tipo_prodotto',
                                                modifyQueryUsing: fn (Builder $query) => $query
                                                    ->where('is_dummy', false)
                                            )
                                            ->searchable()
                                            ->bulkToggleable()
                                            ->columns(7)
                                            ->gridDirection('row'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        // TAB 2: DATI MANDATO E VIGILANZA (OAM / IVASS)
                        Tabs\Tab::make('Mandato & Vigilanza')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Section::make('Dettagli Contratto Mandato')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255)
                                            ->label('Email principale'),
                                        TextInput::make('mandate_number')
                                            ->maxLength(100)
                                            ->label('Protocollo/N° Mandato'),
                                        DatePicker::make('stipulated_at')
                                            ->label('Data Stipula Convenzione'),
                                        DatePicker::make('start_date')
                                            ->label('Data Decorrenza'),
                                        DatePicker::make('end_date')
                                            ->label('Data rinnovo (Vuoto se indeterminato)'),
                                        DatePicker::make('dismissed_at')
                                            ->label('Data Recesso'),

                                        Select::make('submission_type')
                                            ->label('Gestione inoltro')
                                            ->options([

                                                '--' => '--',
                                                'accesso portale' => 'Accentrato',
                                                'inoltro' => 'Decentrato',
                                                'entrambi' => 'Modalita combinata',
                                            ])
                                            ->default('accesso portale')
                                            ->required()
                                            ->label('Modalità Inoltro Pratiche'),
                                        Toggle::make('is_exclusive')
                                            ->inline(false)
                                            ->label('Mandato in Esclusiva'),
                                        Select::make('status')
                                            ->options([
                                                'ATTIVO' => 'Attivo',
                                                'SCADUTO' => 'Scaduto',
                                                'RECEDUTO' => 'Receduto',
                                                'SOSPESO' => 'Sospeso',
                                            ])
                                            ->default('ATTIVO')
                                            ->required()
                                            ->label('Stato Mandato'),
                                    ]),
                                Grid::make(2)->schema([
                                    Section::make('Sezione OAM')
                                        ->columnSpan(1)
                                        ->schema([
                                            TextInput::make('abi')
                                                ->maxLength(30)
                                                ->label('Codice ABI / Numero RUI'),
                                            TextInput::make('abi_name')
                                                ->maxLength(255)
                                                ->label('Nome Ufficiale Banca'),
                                            TextInput::make('oam')
                                                ->maxLength(30)
                                                ->label('Codice Iscrizione OAM'),
                                            TextInput::make('oam_name')
                                                ->maxLength(255)
                                                ->label('Denominazione OAM'),
                                            DatePicker::make('oam_at')
                                                ->label('Data Iscrizione OAM'),
                                            TextInput::make('numero_iscrizione_rui')
                                                ->maxLength(50)
                                                ->label('Numero Iscrizione RUI'),
                                        ]),
                                    Section::make('Sezione IVASS')
                                        ->columnSpan(1)
                                        ->schema([
                                            TextInput::make('ivass')
                                                ->maxLength(30)
                                                ->label('Codice Iscrizione IVASS'),
                                            TextInput::make('ivass_name')
                                                ->maxLength(255)
                                                ->label('Denominazione IVASS'),
                                            DatePicker::make('ivass_at')
                                                ->label('Data Iscrizione IVASS'),
                                            Select::make('ivass_section')
                                                ->options([
                                                    'A' => 'Sezione A',
                                                    'B' => 'Sezione B',
                                                    'C' => 'Sezione C',
                                                    'D' => 'Sezione D',
                                                    'E' => 'Sezione E',
                                                ])
                                                ->label('Sezione IVASS'),
                                        ]),
                                ]),
                            ]),
                        // TAB 3: CONTABILITÀ, PRIVACY E NOTE
                        Tabs\Tab::make('Amministrazione & Privacy')
                            ->icon('heroicon-m-credit-card')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('coge')
                                        ->maxLength(255)
                                        ->label('Codice Contabile COGE'),
                                    TextInput::make('privacy_contact_email')
                                        ->email()
                                        ->maxLength(255)
                                        ->label('Email Contatto Privacy'),
                                    TextInput::make('dpo_email')
                                        ->email()
                                        ->maxLength(255)
                                        ->label('Email DPO (Responsabile protezione dati)'),
                                ]),
                                Toggle::make('is_reported')
                                    ->label('Accordi di Segnalazione Attivi'),
                                Textarea::make('notes')
                                    ->rows(4)
                                    ->label('Note su Provvigioni Particolari o Patti Specifici')
                                    ->placeholder('Inserisci qui accordi extra, sconti o patti di storno...'),
                            ]),
                    ]),
            ])
            ->columns(1);  // Mantiene il contenitore dei Tab a larghezza piena
    }
}

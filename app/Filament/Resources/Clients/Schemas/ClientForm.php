<?php

namespace App\Filament\Unicofin\Resources\Clients\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Model;


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
                                            ->label(fn(Get $get) => $get('is_person') ? 'Cognome / Ragione Sociale' : 'Ragione Sociale')
                                            ->required()
                                            ->maxLength(255),
                                        Toggle::make('is_person')
                                            ->label('Persona Fisica')
                                            ->default(true)
                                            ->live(),  // Ricarica la form al cambio
                                        TextInput::make('first_name')
                                            ->label('Nome')
                                            ->visible(fn(Get $get) => $get('is_person'))  // Scompare se azienda
                                            ->maxLength(255),
                                        TextInput::make('tax_code')
                                            ->label(fn(Get $get) => $get('is_person') ? 'Codice Fiscale' : 'P.IVA')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(16),
                                    ])
                                    ->columns(4),
                                Section::make('Contatti & Origine')
                                    ->schema([
                                        TextInput::make('email')->email(),
                                        TextInput::make('phone')->label('Telefono')->tel(),
                                        Select::make('client_type_id')
                                            ->label('Tipologia')
                                            ->relationship('clientType', 'name')
                                            ->searchable(),
                                    ])
                                    ->columns(3),
                            ]),
                        // --- TAB 4: AMMINISTRAZIONE ---
                        Tab::make('Admin / Stato')
                            ->icon('heroicon-o-cog')
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
                        // --- TAB 2: COMPLIANCE & PRIVACY ---
                        Tab::make('Compliance AML')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Valutazione Rischio (AML)')
                                    ->description('Indicatori di rischio e posizioni critiche')
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
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
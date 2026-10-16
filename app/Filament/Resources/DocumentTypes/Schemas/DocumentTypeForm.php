<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use App\Models\DocumentType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Unico\Core\Documents\Api\DocumentApiService;
use Unico\Core\Documents\DocumentTypeConsistency;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('coerenza')
                    ->label('Controllo di coerenza')
                    ->content(fn (?DocumentType $record): HtmlString => new HtmlString(collect(app(DocumentTypeConsistency::class)->problemsOf($record, app(DocumentApiService::class)->services()))->map(fn (string $problem): string => '⚠️ '.e($problem))->implode('<br>')))
                    ->visible(fn (?DocumentType $record): bool => $record !== null && app(DocumentTypeConsistency::class)->problemsOf($record, app(DocumentApiService::class)->services()) !== [])
                    ->columnSpanFull(),
                Section::make('Documento')
                    ->description('Nome e categoria. A chi va il documento lo decide il plico (task) in cui è richiesto.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Documento')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Textarea::make('description')
                            ->label('Descrizione Aggiuntiva')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('doctype')
                            ->label('Tipo documento')
                            ->options([
                                'modulo' => 'Modulo OAM',
                                'procedura' => 'Procedura OAM',
                                'template' => 'Template',
                                'OAM' => 'Trasp.OAM',
                                'comunicazione' => 'Comunicazione',
                            ]),

                    ]),
                Section::make('Come si ottiene')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Select::make('source')
                            ->label('Fonte')
                            ->options(DocumentType::SOURCES)
                            ->default(DocumentType::SOURCE_CLIENTE)
                            ->required()
                            ->live(),
                        Select::make('api_service')
                            ->label('Servizio API')
                            ->options(fn (): array => app(DocumentApiService::class)->services())
                            ->visible(fn (Get $get): bool => $get('source') === DocumentType::SOURCE_API)
                            ->required(fn (Get $get): bool => $get('source') === DocumentType::SOURCE_API)
                            ->helperText('Nei documenti di questo tipo compare «Richiedi via API». Alcuni servizi sono a pagamento.'),
                        Placeholder::make('modulo_pdf')
                            ->label('Modulo di stampa')
                            ->content(fn (?DocumentType $record): string => $record?->pdfModule?->name ?? 'Nessun modulo PDF: va caricato in Moduli PDF con i campi collegati ai nostri dati.')
                            ->visible(fn (Get $get): bool => $get('source') === DocumentType::SOURCE_STAMPA),
                        TextInput::make('document_url')
                            ->label('URL del template')
                            ->visible(fn (Get $get): bool => $get('source') === DocumentType::SOURCE_TEMPLATE),
                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->label('File del template (PDF, immagini, Word)')
                            ->multiple()
                            ->collection('documents')
                            ->disk('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(20480)
                            ->visible(fn (Get $get): bool => $get('source') === DocumentType::SOURCE_TEMPLATE)
                            ->columnSpanFull(),
                    ]),
                Section::make('Ambito, firma e approvazione')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Select::make('client_scope')
                            ->label('Cliente')
                            ->options(DocumentType::CLIENT_SCOPES)
                            ->placeholder('Entrambi')
                            ->helperText('Per i documenti di clienti e pratiche: persona fisica o giuridica.'),
                        Select::make('clienti_id')
                            ->label('Solo per l\'istituto')
                            ->relationship('clienti', 'name')
                            ->searchable()
                            ->placeholder('Tutti gli istituti'),
                        Toggle::make('is_company')
                            ->label('Documento aziendale interno')
                            ->helperText('Organigramma, verbali CdA: fuori da pratiche, agenti e mandanti.')
                            ->inline(false),
                        Toggle::make('is_sensible')->label('Contiene dati sensibili')->inline(false),
                        Toggle::make('is_signed')->label('Da firmare')->live()->inline(false),
                        Select::make('signer_model')
                            ->label('Chi firma')
                            ->options(self::modelli())
                            ->default('client')
                            ->helperText('Di default il cliente; il plico (task) può indicarne un altro.')
                            ->visible(fn (Get $get): bool => (bool) $get('is_signed')),
                        Select::make('approver_model')
                            ->label('Approvazione con firma di')
                            ->options(self::modelli())
                            ->placeholder('Nessuna approvazione')
                            ->visible(fn (Get $get): bool => (bool) $get('is_signed')),
                        TextInput::make('slug')
                            ->label('Codice documento ')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Caratteristiche scadenza e rinnovo')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        Toggle::make('is_monitored')
                            ->label('Monitora Scadenza')
                            ->live()
                            ->inline(false),
                        Toggle::make('is_versioned')
                            ->label('Mantieni lo storico')
                            ->live()
                            ->inline(false),
                        TextInput::make('duration')
                            ->label('Durata Validità')
                            ->numeric()
                            ->visible(fn ($get) => $get('is_monitored')),
                        Select::make('duration_unit')
                            ->label('Unità di Misura')
                            ->options([
                                'days' => 'Giorni',
                                'months' => 'Mesi',
                                'years' => 'Anni',
                            ])
                            ->default('days')
                            ->visible(fn ($get) => $get('is_monitored')),
                        Toggle::make('is_end_month')
                            ->label('Approssima a Fine Mese')
                            ->visible(fn ($get) => $get('is_monitored')),
                        Select::make('renewed_by_id')
                            ->label('Rinnovato da altro documento')
                            ->relationship('renewedBy', 'name')
                            ->searchable()
                            ->visible(fn ($get) => $get('is_monitored'))
                            ->placeholder('Seleziona tipo...'),
                    ]),
            ]);
    }

    /** @return array<string, string> chi può firmare o approvare: alias del morph map */
    private static function modelli(): array
    {
        return [
            'client' => 'Cliente',
            'fornitore' => 'Agente / produttore',
            'employee' => 'Dipendente',
            'company' => 'Azienda',
            'cliente' => 'Mandante / istituto',
        ];
    }
}

<?php

namespace App\Filament\Resources\RelationManagers;

use Unico\Core\Enums\DocumentStatus;
use Unico\Core\Enums\SignatureRequestStatus;
use App\Filament\Actions\SendForSignatureAction;
use App\Filament\Exports\DynamicGroupExport;
use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\SignatureRequest;
use App\Models\Task;
use App\Services\ModuleDataResolver;
use App\Services\PdfFormException;
use App\Services\PdfFormFiller;
use App\Services\ResolvedModuleData;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\SignatureRequestService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
// CORRETTO
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\ExportAction; // <-- Importa il trait

class DocumentsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;  // <-- Basta questo! Controlla automaticamente checkPiano('websites')

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documenti';

    /**
     * Plichi (task) attivi che corrispondono al tipo e allo stato del record su cui siamo.
     *
     * @return Collection<int, Task>
     */
    private function applicablePlichi(): Collection
    {
        return Task::getAvailableFor($this->getOwnerRecord())->load('documentTypes')->values();
    }

    /**
     * Flag di DocumentType che abilita il tipo per il modello su cui il relation manager e' montato.
     *
     * @var array<class-string<Model>, string>
     */
    private const OWNER_TYPE_FLAGS = [
        Client::class => 'is_client',
        Pratica::class => 'is_practice',
        Clienti::class => 'is_principal',
        Fornitore::class => 'is_agent',
        Employee::class => 'is_employee',
        Company::class => 'is_company',
    ];

    /**
     * Tipi documento proponibili per il modello corrente (tutti se il modello non e' mappato).
     * Nel form sono esclusi i tipi gia' presenti sui documenti del record (tranne quello del documento in modifica); nel filtro restano anche quelli gia' usati.
     *
     * @return array<int, string>
     */
    private function documentTypeOptions(bool $includeUsed = false, mixed $currentId = null, bool $excludeUsed = false): array
    {
        $flag = self::OWNER_TYPE_FLAGS[$this->getOwnerRecord()::class] ?? null;

        return DocumentType::query()
            ->when($flag !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($flag, $includeUsed, $currentId): void {
                $query->where($flag, true)
                    ->when($currentId, fn (Builder $query) => $query->orWhereKey($currentId))
                    ->when($includeUsed, fn (Builder $query) => $query->orWhereIn('id', $this->getOwnerRecord()->documents()->withTrashed()->select('document_type_id')));
            }))
            ->when($excludeUsed, fn (Builder $query) => $query
                ->whereNotIn('id', $this->getOwnerRecord()->documents()->whereNotNull('document_type_id')->select('document_type_id'))
                ->when($currentId, fn (Builder $query) => $query->orWhereKey($currentId)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dettagli Documento')
                ->columnSpanFull() // <--- Occupa tutto lo spazio orizzontale della pagina/modal
                ->columns(2)       // <--- Organizza i componenti interni su 2 colonne
                ->components([
                    Select::make('document_type_id')

                        ->label('Tipo documento')
                        ->options(fn (?Document $record): array => $this->documentTypeOptions(currentId: $record?->document_type_id, excludeUsed: true))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, $get): void {
                            if (blank($get('name'))) {
                                $documentType = DocumentType::find($state);
                                $set('name', $documentType?->name);
                                $set('is_monitored', $documentType?->is_monitored);
                                $set('doctype', $documentType?->doctype);
                            }
                        })
                        //  ->required()
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label('Nome / Titolo')
                        ->default(fn ($get) => $get('document_type_id') ? DocumentType::find($get('document_type_id'))->name : null)
                        ->required()
                        ->columnSpanFull(),
                    /*
                    Select::make('status')
                        ->label('Stato')
                        ->options(DocumentStatus::class)
                        ->default(DocumentStatus::PENDING),
                      */

                    DatePicker::make('emitted_at')
                        ->label('Data emissione')
                        ->live()
                        //  ->visible(fn($get) => $get('is_monitored'))
                        ->displayFormat('d/m/y'),
                    Toggle::make('is_monitored')
                        ->label('Controlla scadenza')
                        ->default(fn ($get) => $get('document_type_id') ? DocumentType::find($get('document_type_id'))->is_monitored : false)

                        ->live(),
                    DatePicker::make('expires_at')
                        ->label('Data scadenza')
                        ->default(fn ($get) => $get('document_type_id') ? DocumentType::find($get('document_type_id'))->durationCalculate($get('emitted_at')) : null)
                        ->displayFormat('d/m/y')
                        ->visible(fn ($get) => $get('is_monitored'))
                        ->afterOrEqual('emitted_at'),
                    TextInput::make('docnumber')
                        ->label('Protocollo documento')
                        ->placeholder('es. CI-2024-001'),
                    /*
                    Select::make('doctype')
                        ->label('Tipo documento')
                        ->options([
                            'modulo' => 'Modulo',
                            'procedura' => 'Procedura',
                            'template' => 'Template',
                        ]),

                    Textarea::make('description')
                        ->label('Descrizione supplementare')
                        ->rows(2)
                        ->columnSpanFull(),
                    Textarea::make('internal_notes')
                        ->label('Note interne')
                        ->rows(2)
                        ->columnSpanFull(),
                        */
                ]),
            Section::make('File Allegato')
                ->columnSpanFull()
                ->components([
                    TextInput::make('document_url')
                        ->label('URL documento')
                        ->url(fn ($record) => $record?->document_url ? (str_starts_with($record->document_url, 'http') ? $record->document_url : "https://{$record->document_url}") : null),
                    SpatieMediaLibraryFileUpload::make('attachments')
                        ->label('Carica file (PDF, immagini, Word)')
                        ->multiple()
                        ->collection('documents')
                        ->disk('public')
                        ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(20480)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])->with('signatureRequests'))
            ->defaultSort('expires_at', 'desc')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->default('Senza documento')
                    ->html()
                    ->formatStateUsing(function ($state, Document $record) {
                        $url = $record->getFirstMedia('documents')
                            ? route('documents.download', $record)
                            : (! empty($record->document_url) ? $record->document_url : null);

                        if (! $url) {
                            return $state;
                        }

                        return sprintf(
                            '<a href="%s" target="_blank" style="color:#2563eb;text-decoration:underline;">%s</a>',
                            e($url),
                            e($state)
                        );
                    }),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('emitted_at')
                    ->label('Emissione')
                    ->date('d/m/y')
                    //  ->visible(fn($record) => $record?->is_monitored)
                    ->sortable(),
                TextColumn::make('signature_state')
                    ->label('Firma')
                    ->badge()
                    ->state(fn (Document $record): string => self::signatureState($record))
                    ->color(fn (string $state): string => match ($state) {
                        'In attesa di firma' => 'info',
                        'Firmato' => 'success',
                        'Rifiutata', 'Scaduta' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('otp')
                    ->label('OTP')
                    ->badge()
                    ->state(fn (Document $record): ?string => self::otpState($record))
                    ->color(fn (?string $state): string => $state === 'Invia OTP' ? 'primary' : 'info')
                    ->icon(fn (?string $state): ?string => $state === 'Invia OTP' ? 'heroicon-o-device-phone-mobile' : null)
                    ->action(SendForSignatureAction::make(
                        onlyForSignedTypes: true,
                        preflight: fn (Document $document): ?string => self::missingSignerContacts($document),
                    )),
            ])
            ->filters([
                SelectFilter::make('document_type_id')
                    ->label('Tipo documento')
                    ->options(fn (): array => $this->documentTypeOptions(includeUsed: true))
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->multiple()
                    ->options(DocumentStatus::class),
                SelectFilter::make('doctype')
                    ->label('Tipo documento')
                    ->multiple()
                    ->options([
                        'modulo' => 'Modulo',
                        'procedura' => 'Procedura',
                        'informativa' => 'Informativa',
                        'template' => 'Template',
                    ]),
                Filter::make('plico_attuale')
                    ->label('Plico dello stato attuale')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereIn(
                        'document_type_id',
                        $this->applicablePlichi()->flatMap(fn (Task $task) => $task->documentTypes->pluck('id'))->unique()->values()->all(),
                    )),
                Filter::make('is_monitored')
                    ->label('Monitorato')
                    ->query(fn ($query) => $query->where('is_monitored', true)),
                TernaryFilter::make('is_expired')
                    ->label('Scaduto')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query->where('status', DocumentStatus::EXPIRED->value),
                        false: fn ($query) => $query->where('status', '!=', DocumentStatus::EXPIRED->value),
                    ),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('generaPlico')
                    ->label('Genera plico')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('gray')
                    ->modalHeading('Genera plico documentale')
                    ->modalDescription('Vengono creati, in stato "richiesto", i documenti dei plichi applicabili che non sono ancora presenti.')
                    ->modalSubmitActionLabel('Genera')
                    ->mountUsing(function (Action $action): void {
                        if ($this->applicablePlichi()->isEmpty()) {
                            Notification::make()->title('Nessun plico applicabile')->body('Per lo stato attuale di questo record non ci sono plichi documentali.')->warning()->send();

                            $action->cancel();
                        }
                    })
                    ->schema(fn (): array => [
                        CheckboxList::make('plichi')
                            ->label('Plichi')
                            ->options(fn (): array => $this->applicablePlichi()->mapWithKeys(fn (Task $task): array => [$task->getKey() => $task->name])->all())
                            ->descriptions(fn (): array => $this->applicablePlichi()->mapWithKeys(fn (Task $task): array => [$task->getKey() => $task->documentTypes->count().' documenti ('.$task->documentTypes->filter(fn ($type) => $type->pivot->is_required)->count().' obbligatori)'])->all())
                            ->default(fn (): array => $this->applicablePlichi()->pluck('id')->all())
                            ->required()
                            ->columns(1),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $created = 0;
                        $existing = 0;

                        foreach ($this->applicablePlichi()->whereIn('id', $data['plichi'] ?? []) as $task) {
                            $result = $task->generateDocumentsFor($owner);
                            $created += $result['created'];
                            $existing += $result['existing'];
                        }

                        Notification::make()
                            ->title($created.' documenti richiesti creati')
                            ->body($existing > 0 ? $existing.' erano già presenti.' : null)
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id
                            ?? $this->getOwnerRecord()->id;

                        return $data;
                    }),
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make(),
                    ])
                    ->label('Esporta Excel')
                    ->color('success'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refreshSignature')
                    ->label('Aggiorna stato')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Document $record): bool => self::openSignatureRequest($record) !== null)
                    ->action(function (Document $record): void {
                        $request = self::openSignatureRequest($record);

                        if ($request === null) {
                            return;
                        }

                        try {
                            $request = app(SignatureRequestService::class)->reconcile($request);
                        } catch (SignatureRequestException $e) {
                            Notification::make()->danger()->title('Aggiornamento non riuscito')->body($e->getMessage())->send();

                            return;
                        }

                        if ($request->status === SignatureRequestStatus::Signed) {
                            Notification::make()->success()->title('Documento firmato')->send();
                        } elseif ($request->status->isOpen()) {
                            Notification::make()->info()->title('Ancora in attesa di firma')->send();
                        } else {
                            Notification::make()->warning()->title('Richiesta di firma: '.mb_strtolower($request->status->getLabel()))->send();
                        }
                    }),
                Action::make('cancelSignature')
                    ->label('Annulla richiesta di firma')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record): bool => self::openSignatureRequest($record) !== null)
                    ->action(function (Document $record): void {
                        $request = self::openSignatureRequest($record);

                        if ($request === null) {
                            return;
                        }

                        try {
                            app(SignatureRequestService::class)->cancel($request, auth()->user());
                        } catch (SignatureRequestException $e) {
                            Notification::make()->danger()->title('Annullamento non riuscito')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Richiesta di firma annullata')->send();
                    }),
                Action::make('downloadDocument')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Document $record): bool => ! $record->is_signed && self::downloadKind($record) !== null)
                    ->action(fn (Document $record) => self::download($record)),
                Action::make('downloadSigned')
                    ->label('Scarica firmato')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Document $record): bool => $record->is_signed && $record->getFirstMedia('documents') !== null)
                    ->action(function (Document $record) {
                        $media = $record->getFirstMedia('documents');

                        return $media === null ? null : response()->download($media->getPath(), $media->file_name);
                    }),
                /*
                Action::make('renew')
                    ->label('Aggiorna')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Document $record) => "Aggiorna documento: {$record->name}")
                    ->modalDescription(fn (Document $record) => "Sei sicuro di voler aggiornare \"{$record->name}\"?")
                    ->action(function (Document $record) {
                        // Chiamiamo il metodo direttamente sul model
                        $record->renew();

                        Notification::make()
                            ->title('Aggiornamento effettuato')
                            ->body("Nuovo aggiornamento generato con successo per \"{$record->name}\".")
                            ->success()
                            ->send();
                    }),
                    */
                //  DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setEmittedAt')
                        ->label('Imposta Data Emissione')
                        ->icon('heroicon-o-calendar')
                        ->color('success')
                        ->form([
                            DatePicker::make('emitted_at')
                                ->label('Data di Emissione')
                                ->required()
                                ->default(now()), // Imposta la data odierna come default
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $dataForced = $data['emitted_at'] ?? now(); // Usa la data fornita o la data odierna come fallback

                                $record->update([
                                    'emitted_at' => $dataForced,
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion() // Deseleziona i record dopo l'operazione
                        ->requiresConfirmation()
                        ->modalHeading('Imposta data di emissione per i record selezionati')
                        ->modalSubmitActionLabel('Salva'),
                    BulkAction::make('setExpiredAt')
                        ->label('Imposta Data Scadenza')
                        ->icon('heroicon-o-calendar')
                        ->color('success')
                        ->form([
                            DatePicker::make('expired_at')
                                ->label('Data di Scadenza')
                                ->required()
                                ->default(now()), // Imposta la data odierna come default
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'expired_at' => $data['expired_at'],
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion() // Deseleziona i record dopo l'operazione
                        ->requiresConfirmation()
                        ->modalHeading('Imposta data di emissione per i record selezionati')
                        ->modalSubmitActionLabel('Salva'),

                    // DeleteBulkAction::make(),
                    //  ForceDeleteBulkAction::make(),
                    //  RestoreBulkAction::make(),
                ]),
            ]);

    }

    /**
     * 'module' se il tipo ha un nostro modulo PDF attivo compilabile per il proprietario,
     * 'template' se e' un template con un file scaricabile, altrimenti null.
     */
    private static function downloadKind(Document $document): ?string
    {
        $type = $document->documentType;

        if ($type === null) {
            return null;
        }

        if ($type->pdfModule?->is_active && self::canPrintModule($document)) {
            return 'module';
        }

        if (($type->is_template || $document->is_template) && ($document->getFirstMedia('documents') !== null || filled($document->document_url))) {
            return 'template';
        }

        return null;
    }

    private static function moduleDataFor(Document $document): ?ResolvedModuleData
    {
        $owner = $document->documentable;
        $resolver = app(ModuleDataResolver::class);

        if ($owner instanceof Client) {
            return $resolver->resolveForClient($owner);
        }

        $client = $owner instanceof Pratica ? $resolver->findClient($owner) : null;

        return $client === null ? null : $resolver->resolve($owner, $client);
    }

    /**
     * Il modulo si stampa solo sul modello a cui il tipo documento e' destinato:
     * cliente (is_client) o pratica (is_practice, con cliente collegato).
     */
    private static function canPrintModule(Document $document): bool
    {
        $owner = $document->documentable;
        $type = $document->documentType;

        return match (true) {
            $owner instanceof Client => (bool) $type?->is_client,
            $owner instanceof Pratica => (bool) $type?->is_practice && app(ModuleDataResolver::class)->findClient($owner) !== null,
            default => false,
        };
    }

    private static function download(Document $document): mixed
    {
        if (self::downloadKind($document) === 'module') {
            $module = $document->documentType->pdfModule;
            $data = self::moduleDataFor($document);

            try {
                $content = app(PdfFormFiller::class)->fill($module, $data);
            } catch (PdfFormException $e) {
                report($e);
                Notification::make()->danger()->title('Stampa non disponibile')->body($e->getMessage())->send();

                return null;
            }

            return response()->streamDownload(fn () => print ($content), Str::slug($module->name).'.pdf', ['Content-Type' => 'application/pdf']);
        }

        $media = $document->getFirstMedia('documents');

        if ($media !== null) {
            return response()->download($media->getPath(), $media->file_name);
        }

        return redirect()->away($document->document_url);
    }

    /**
     * Usa la relazione gia' caricata dalla query della tabella (niente N+1).
     */
    private static function latestSignatureRequest(Document $document): ?SignatureRequest
    {
        return $document->signatureRequests->sortByDesc('id')->first();
    }

    private static function openSignatureRequest(Document $document): ?SignatureRequest
    {
        $request = self::latestSignatureRequest($document);

        return $request?->isOpen() ? $request : null;
    }

    /**
     * 'Invia OTP' se il documento (tipo con firma) e' ancora da firmare, 'OTP inviato' con richiesta aperta.
     */
    private static function otpState(Document $document): ?string
    {
        if ($document->is_signed || ! $document->documentType?->is_signed) {
            return null;
        }

        return self::openSignatureRequest($document) !== null ? 'OTP inviato' : 'Invia OTP';
    }

    /**
     * Avviso se il cliente del documento non ha cellulare ed email: l'OTP (SMS + email) non puo' partire.
     */
    private static function missingSignerContacts(Document $document): ?string
    {
        $owner = $document->documentable;
        $client = $owner instanceof Pratica ? app(ModuleDataResolver::class)->findClient($owner) : $owner;

        if (! $client instanceof Client) {
            return null;
        }

        $missing = collect(['phone' => 'cellulare', 'email' => 'email'])
            ->filter(fn (string $label, string $field): bool => blank($client->{$field}))
            ->values();

        return $missing->isEmpty()
            ? null
            : 'Mancano '.$missing->implode(' ed ').' del cliente: completare l\'anagrafica prima di inviare l\'OTP.';
    }

    private static function signatureState(Document $document): string
    {
        if ($document->is_signed) {
            return 'Firmato';
        }

        $request = self::latestSignatureRequest($document);

        return match ($request?->status) {
            SignatureRequestStatus::Pending, SignatureRequestStatus::Sent => 'In attesa di firma',
            SignatureRequestStatus::Declined => 'Rifiutata',
            SignatureRequestStatus::Expired => 'Scaduta',
            SignatureRequestStatus::Cancelled => 'Annullata',
            SignatureRequestStatus::Failed => 'Non riuscita',
            default => 'Non firmato',
        };
    }
}

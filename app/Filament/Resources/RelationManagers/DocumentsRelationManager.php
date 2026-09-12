<?php

namespace App\Filament\Resources\RelationManagers;

use App\Enums\DocumentStatus;
use App\Filament\Exports\DynamicGroupExport;
use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Document;
use App\Models\DocumentType;
use App\ValueObjects\OamSemester;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\ExportAction; // <-- Importa il trait

class DocumentsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;  // <-- Basta questo! Controlla automaticamente checkPiano('websites')

    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documenti';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dettagli Documento')
                ->columnSpanFull() // <--- Occupa tutto lo spazio orizzontale della pagina/modal
                ->columns(2)       // <--- Organizza i componenti interni su 2 colonne
                ->components([
                    Select::make('document_type_id')

                        ->label('Tipo documento')
                        ->options(DocumentType::orderBy('name')->pluck('name', 'id'))
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
            ]))
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
                TextColumn::make('expires_at')
                    ->label('Scadenza')
                    ->date('d/m/y')
                    ->sortable()
                  //  ->visible(fn ($record) => $record?->is_monitored ?? false)
                    ->color(fn ($record) => $record?->expires_at?->isPast() ? 'danger' : 'gray')
                    ->weight(fn ($record) => $record?->expires_at?->isPast() ? 'bold' : 'normal'),
                TextColumn::make('doctype')
                    ->sortable()
                    ->label('Tipo documento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'modulo' => 'info',
                        'procedura' => 'warning',
                        'template' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

            ])
            ->filters([
                Filter::make('semestre_attuale')
                    ->label('Solo semestre in corso')
                    ->toggle() // <--- Trasforma la Checkbox in un interruttore Toggle grafico
                    ->default(true)
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['isActive']
                            ? $query->perSemestreOam(OamSemester::getInBaseAlMeseCorrente())
                            : $query;
                    }),
                SelectFilter::make('document_type_id')
                    ->label('Tipo documento')
                    ->relationship('documentType', 'name')
                    ->searchable()
                    ->preload(),
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
}

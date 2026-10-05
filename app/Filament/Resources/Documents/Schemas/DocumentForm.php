<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

// CORRETTO

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dettagli Documento')
                //  ->columns(2)
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
                            }
                        })
                        //  ->required()
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label('Nome / Titolo')
                        ->default(fn ($get) => $get('document_type_id') ? DocumentType::find($get('document_type_id'))->name : null)
                        ->required()
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label('Stato')
                        ->options(DocumentStatus::class)
                        ->default(DocumentStatus::PENDING)
                        ->required(),
                    Select::make('doctype')
                        ->label('Tipologia documento')
                        ->options([
                            'modulo' => 'Modulo',
                            'procedura' => 'Procedura',
                            'template' => 'Template',
                        ]),
                    DatePicker::make('emitted_at')
                        ->label('Data emissione')
                        ->live()
                        //  ->visible(fn($get) => $get('is_monitored'))
                        ->displayFormat('d/m/y'),
                    Toggle::make('is_monitored')
                        ->label('Controlla scadenza')
                        ->default(false)
                        ->live(),
                    DatePicker::make('expires_at')
                        ->label('Data scadenza')
                        ->default(fn ($get) => $get('document_type_id') ? DocumentType::find($get('document_type_id'))->durationCalculate($get('emitted_at')) : null)
                        ->displayFormat('d/m/y')
                        ->visible(fn ($get) => $get('is_monitored'))
                        ->afterOrEqual('emitted_at'),
                    TextInput::make('docnumber')
                        ->label('Numero documento')
                        ->placeholder('es. CI-2024-001'),
                    Textarea::make('description')
                        ->label('Descrizione supplementare')
                        ->rows(2)
                        ->columnSpanFull(),
                    Textarea::make('internal_notes')
                        ->label('Note interne')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Section::make('File Allegato')
                ->components([
                    TextInput::make('document_url')
                        ->label('URL documento')
                        ->url(fn ($record) => $record?->document_url ? (str_starts_with($record->document_url, 'http') ? $record->document_url : "https://{$record->document_url}") : null),

                ]),
        ]);
    }
}

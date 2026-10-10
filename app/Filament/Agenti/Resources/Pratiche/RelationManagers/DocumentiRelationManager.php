<?php

namespace App\Filament\Agenti\Resources\Pratiche\RelationManagers;

use Unico\Core\Enums\DocumentStatus;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Models\DocumentType;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Documenti della pratica: l'agente li consulta e ne carica di nuovi, senza modifiche o cancellazioni.
 */
class DocumentiRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewPraticaAgente::class;
    }

    /**
     * Anche nella pagina di visualizzazione l'agente puo' aggiungere documenti (ma non modificarli).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_type_id')
                ->label('Tipo documento')
                ->options(fn (): array => DocumentType::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            SpatieMediaLibraryFileUpload::make('attachments')
                ->label('File (PDF o immagine)')
                ->collection('documents')
                ->disk('public')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(20480)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Documento'),
                TextColumn::make('status')->label('Stato')->badge(),
                TextColumn::make('created_at')->label('Caricato il')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Aggiungi documento')
                    ->mutateDataUsing(function (array $data): array {
                        $type = DocumentType::query()->findOrFail($data['document_type_id']);

                        return [
                            'document_type_id' => $type->getKey(),
                            'name' => $type->name,
                            'status' => DocumentStatus::UPLOADED->value,
                            'spatie_collection' => 'documents',
                            'uploaded_by' => Auth::id(),
                            'created_by' => Auth::id(),
                        ];
                    }),
            ]);
    }
}

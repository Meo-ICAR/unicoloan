<?php

namespace App\Filament\Resources\DocumentVerifications;

use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentVerifications\Pages\ListDocumentVerifications;
use App\Filament\Resources\DocumentVerifications\Tables\DocumentVerificationsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Coda di verifica: documenti caricati in attesa che un operatore li accetti o li rifiuti.
 */
class DocumentVerificationResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Documenti da verificare';

    protected static ?string $modelLabel = 'Documento da verificare';

    protected static ?string $pluralModelLabel = 'Documenti da verificare';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', DocumentStatus::UPLOADED->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return DocumentVerificationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentVerifications::route('/'),
        ];
    }
}

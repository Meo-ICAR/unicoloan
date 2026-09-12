<?php

namespace App\Filament\Unicofin\Resources\Praticas;

use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Unicofin\Resources\Praticas\Pages\CreatePratica;
use App\Filament\Unicofin\Resources\Praticas\Pages\EditPratica;
use App\Filament\Unicofin\Resources\Praticas\Pages\ListPraticas;
use App\Filament\Unicofin\Resources\Praticas\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\Praticas\RelationManagers\RequisitiRelationManager;
use App\Filament\Unicofin\Resources\Praticas\Schemas\PraticaForm;
use App\Filament\Unicofin\Resources\Praticas\Tables\PraticasTable;
use App\Models\PROFORMA\Pratica;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table; // <--- AGGIUNTO QUESTO IMPORT MANCANTE

class PraticaResource extends Resource
{
    protected static ?string $model = Pratica::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';  // Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

    // protected static UnitEnum|string|null $navigationGroup = 'Pratiche';

    // protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'cognome_cliente';

    public static function form(Schema $schema): Schema
    {
        return PraticaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraticasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RequisitiRelationManager::class,
            DocumentsRelationManager::class,
            ProvvigioniRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraticas::route('/'),
            'create' => CreatePratica::route('/create'),
            'edit' => EditPratica::route('/{record}/edit'),
        ];
    }
}

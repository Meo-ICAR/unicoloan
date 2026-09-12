<?php

namespace App\Filament\Unicofin\Resources\Fornitores;

use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Unicofin\Resources\Fornitores\Pages\CreateFornitore;
use App\Filament\Unicofin\Resources\Fornitores\Pages\EditFornitore;
use App\Filament\Unicofin\Resources\Fornitores\Pages\ListFornitores;
use App\Filament\Unicofin\Resources\Fornitores\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\Fornitores\Schemas\FornitoreForm;
use App\Filament\Unicofin\Resources\Fornitores\Tables\FornitoresTable;
use App\Models\PROFORMA\Fornitore;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FornitoreResource extends Resource
{
    protected static ?string $model = Fornitore::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    // Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static UnitEnum|string|null $navigationGroup = 'Anagrafiche';

    protected static ?string $navigationLabel = 'Produttori';

    protected static ?string $modelLabel = 'Produttore';

    protected static ?string $pluralModelLabel = 'Produttori';

    // protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return FornitoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FornitoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProvvigioniRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFornitores::route('/'),
            'create' => CreateFornitore::route('/create'),
            'edit' => EditFornitore::route('/{record}/edit'),
        ];
    }
}

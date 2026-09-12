<?php

namespace App\Filament\Unicofin\Resources\Clientis;

use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\RelationManagers\WebsitesRelationManager;
use App\Filament\Unicofin\Resources\Clientis\Pages\CreateClienti;
use App\Filament\Unicofin\Resources\Clientis\Pages\EditClienti;
use App\Filament\Unicofin\Resources\Clientis\Pages\ListClientis;
use App\Filament\Unicofin\Resources\Clientis\RelationManagers\AgentiBlacklistatiRelationManager;
use App\Filament\Unicofin\Resources\Clientis\RelationManagers\LimitsRelationManager;
use App\Filament\Unicofin\Resources\Clientis\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\Clientis\Schemas\ClientiForm;
use App\Filament\Unicofin\Resources\Clientis\Tables\ClientisTable;
use App\Models\PROFORMA\Clienti;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ClientiResource extends Resource
{
    protected static ?string $model = Clienti::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static UnitEnum|string|null $navigationGroup = 'Anagrafiche';

    protected static ?string $navigationLabel = 'Istituti';

    protected static ?string $modelLabel = 'Istituto';

    protected static ?string $pluralModelLabel = 'Istituti';

    // protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClientiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

            ProvvigioniRelationManager::class,
            DocumentsRelationManager::class,
            LimitsRelationManager::class,
            WebsitesRelationManager::class,
            AgentiBlacklistatiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientis::route('/'),
            'create' => CreateClienti::route('/create'),
            'edit' => EditClienti::route('/{record}/edit'),
        ];
    }
}

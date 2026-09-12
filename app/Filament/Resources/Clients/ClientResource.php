<?php

namespace App\Filament\Unicofin\Resources\Clients;

use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Unicofin\Resources\Clients\Pages\CreateClient;
use App\Filament\Unicofin\Resources\Clients\Pages\EditClient;
use App\Filament\Unicofin\Resources\Clients\Pages\ListClients;
use App\Filament\Unicofin\Resources\Clients\RelationManagers\ClientMandatesRelationManager;
use App\Filament\Unicofin\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Filament\Unicofin\Resources\Clients\Schemas\ClientForm;
use App\Filament\Unicofin\Resources\Clients\Tables\ClientsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum; // <--- AGGIUNTO QUESTO IMPORT MANCANTE

class ClientResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    //   protected static string|UnitEnum|null $navigationGroup = 'Pratiche';

    protected static ?string $navigationLabel = 'Anagrafiche';

    protected static ?string $modelLabel = 'Anagrafica';

    protected static ?string $pluralModelLabel = 'Anagrafiche';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // AddressesRelationManager::class,
            DocumentsRelationManager::class,
            //   WebsitesRelationManager::class,
            ClientRelationsRelationManager::class,
            ClientMandatesRelationManager::class,
            //   ChecklistsRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}

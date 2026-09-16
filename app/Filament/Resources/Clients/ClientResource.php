<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\ClientMandatesRelationManager;
use App\Filament\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Tables\ClientsTable;
use App\Filament\Resources\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\RelationManagers\WebsitesRelationManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    // Volutamente senza navigationGroup: Filament mette le voci senza gruppo
    // in cima alla sidebar, prima di tutti i gruppi collassabili — qui serve
    // perché Client è l'anagrafica più usata dell'app.
    protected static ?string $navigationLabel = 'Clienti';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clienti';

    protected static ?int $navigationSort = 2;

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
            ClientRelationsRelationManager::class,
            ClientMandatesRelationManager::class,
            WebsitesRelationManager::class,
            BranchesRelationManager::class,
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

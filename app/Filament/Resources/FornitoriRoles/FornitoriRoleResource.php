<?php

namespace App\Filament\Unicofin\Resources\FornitoriRoles;

use App\Filament\Unicofin\Resources\FornitoriRoles\Pages\CreateFornitoriRole;
use App\Filament\Unicofin\Resources\FornitoriRoles\Pages\EditFornitoriRole;
use App\Filament\Unicofin\Resources\FornitoriRoles\Pages\ListFornitoriRoles;
use App\Filament\Unicofin\Resources\FornitoriRoles\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\FornitoriRoles\Schemas\FornitoriRoleForm;
use App\Filament\Unicofin\Resources\FornitoriRoles\Tables\FornitoriRolesTable;
use App\Models\FornitoriRole;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class FornitoriRoleResource extends Resource
{
    protected static ?string $model = FornitoriRole::class;

    protected static ?string $navigationLabel = 'Tipo Produttori';

    protected static ?string $modelLabel = 'Tipo';

    protected static ?string $pluralModelLabel = 'Tipi';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FornitoriRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FornitoriRolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProvvigioniRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFornitoriRoles::route('/'),
            'create' => CreateFornitoriRole::route('/create'),
            'edit' => EditFornitoriRole::route('/{record}/edit'),
        ];
    }
}

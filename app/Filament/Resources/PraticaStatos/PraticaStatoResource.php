<?php

namespace App\Filament\Unicofin\Resources\PraticaStatos;

use App\Filament\Unicofin\Resources\PraticaStatos\Pages\CreatePraticaStato;
use App\Filament\Unicofin\Resources\PraticaStatos\Pages\EditPraticaStato;
use App\Filament\Unicofin\Resources\PraticaStatos\Pages\ListPraticaStatos;
use App\Filament\Unicofin\Resources\PraticaStatos\Schemas\PraticaStatoForm;
use App\Filament\Unicofin\Resources\PraticaStatos\Tables\PraticaStatosTable;
use App\Models\PraticaStato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PraticaStatoResource extends Resource
{
    protected static ?string $model = PraticaStato::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    // Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Stato Pratica';

    protected static ?string $modelLabel = 'Stato';

    protected static ?string $pluralModelLabel = 'Stati';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PraticaStatoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraticaStatosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraticaStatos::route('/'),
            'create' => CreatePraticaStato::route('/create'),
            'edit' => EditPraticaStato::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

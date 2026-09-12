<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos;

use App\Filament\Unicofin\Resources\Tipoprodottos\Pages\CreateTipoprodotto;
use App\Filament\Unicofin\Resources\Tipoprodottos\Pages\EditTipoprodotto;
use App\Filament\Unicofin\Resources\Tipoprodottos\Pages\ListTipoprodottos;
use App\Filament\Unicofin\Resources\Tipoprodottos\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\Tipoprodottos\RelationManagers\SubproductsRelationManager;
use App\Filament\Unicofin\Resources\Tipoprodottos\Schemas\TipoprodottoForm;
use App\Filament\Unicofin\Resources\Tipoprodottos\Tables\TipoprodottosTable;
use App\Models\Tipoprodotto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TipoprodottoResource extends Resource
{
    protected static ?string $model = Tipoprodotto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Prodotti';

    protected static ?string $modelLabel = 'Prodotto';

    protected static ?string $pluralModelLabel = 'Prodotti';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TipoprodottoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoprodottosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubproductsRelationManager::class,
            ProvvigioniRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTipoprodottos::route('/'),
            'create' => CreateTipoprodotto::route('/create'),
            'edit' => EditTipoprodotto::route('/{record}/edit'),
        ];
    }
}

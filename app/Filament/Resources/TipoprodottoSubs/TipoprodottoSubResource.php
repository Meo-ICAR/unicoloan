<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubs;

// Collega la risorsa figlia alla risorsa padre
use App\Filament\Unicofin\Resources\Tipoprodottos\TipoprodottoResource;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\Pages\CreateTipoprodottoSub;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\Pages\EditTipoprodottoSub;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\Pages\ListTipoprodottoSubs;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\RelationManagers\LimitsRelationManager;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\RelationManagers\ProvvigioniRelationManager;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\Schemas\TipoprodottoSubForm;
use App\Filament\Unicofin\Resources\TipoprodottoSubs\Tables\TipoprodottoSubsTable;
use App\Models\TipoprodottoSub;
use BackedEnum;
use Filament\Resources\ParentResourceRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TipoprodottoSubResource extends Resource
{
    protected static ?string $model = TipoprodottoSub::class;

    // Collega la risorsa figlia alla risorsa padre
    protected static ?string $parentResource = TipoprodottoResource::class;

    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return static::getParentResource()::asParent(childResource: static::class)
            ->relationship('subproducts')
            ->inverseRelationship('tipoProdotto');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Sub Prodotto';

    protected static ?string $pluralModelLabel = 'Sub Prodotti';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TipoprodottoSubForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoprodottoSubsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LimitsRelationManager::class,

            ProvvigioniRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTipoprodottoSubs::route('/'),
            'create' => CreateTipoprodottoSub::route('/create'),
            'edit' => EditTipoprodottoSub::route('/{record}/edit'),
        ];
    }
}

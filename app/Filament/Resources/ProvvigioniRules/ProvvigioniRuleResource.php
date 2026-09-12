<?php

namespace App\Filament\Unicofin\Resources\ProvvigioniRules;

use App\Filament\Unicofin\Resources\ProvvigioniRules\Pages\CreateProvvigioniRule;
use App\Filament\Unicofin\Resources\ProvvigioniRules\Pages\EditProvvigioniRule;
use App\Filament\Unicofin\Resources\ProvvigioniRules\Pages\ListProvvigioniRules;
use App\Filament\Unicofin\Resources\ProvvigioniRules\Schemas\ProvvigioniRuleForm;
use App\Filament\Unicofin\Resources\ProvvigioniRules\Tables\ProvvigioniRulesTable;
use App\Models\ProvvigioniRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProvvigioniRuleResource extends Resource
{
    protected static ?string $model = ProvvigioniRule::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProvvigioniRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvvigioniRulesTable::configure($table);
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
            'index' => ListProvvigioniRules::route('/'),
            'create' => CreateProvvigioniRule::route('/create'),
            'edit' => EditProvvigioniRule::route('/{record}/edit'),
        ];
    }
}

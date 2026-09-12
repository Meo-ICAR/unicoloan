<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubConstraints;

use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Pages\CreateTipoprodottoSubConstraint;
use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Pages\EditTipoprodottoSubConstraint;
use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Pages\ListTipoprodottoSubConstraints;
use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Schemas\TipoprodottoSubConstraintForm;
use App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Tables\TipoprodottoSubConstraintsTable;
use App\Models\TipoprodottoSubConstraint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TipoprodottoSubConstraintResource extends Resource
{
    protected static ?string $model = TipoprodottoSubConstraint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Vincolo';

    protected static ?string $pluralModelLabel = 'Vincoli';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return TipoprodottoSubConstraintForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoprodottoSubConstraintsTable::configure($table);
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
            'index' => ListTipoprodottoSubConstraints::route('/'),
            'create' => CreateTipoprodottoSubConstraint::route('/create'),
            'edit' => EditTipoprodottoSubConstraint::route('/{record}/edit'),
        ];
    }
}

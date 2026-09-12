<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitoOperativos;

use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Pages\CreatePraticaRequisitoOperativo;
use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Pages\EditPraticaRequisitoOperativo;
use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Pages\ListPraticaRequisitoOperativos;
use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Schemas\PraticaRequisitoOperativoForm;
use App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Tables\PraticaRequisitoOperativosTable;
use App\Models\PraticaRequisitoOperativo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PraticaRequisitoOperativoResource extends Resource
{
    protected static ?string $model = PraticaRequisitoOperativo::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PraticaRequisitoOperativoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraticaRequisitoOperativosTable::configure($table);
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
            'index' => ListPraticaRequisitoOperativos::route('/'),
            'create' => CreatePraticaRequisitoOperativo::route('/create'),
            'edit' => EditPraticaRequisitoOperativo::route('/{record}/edit'),
        ];
    }
}

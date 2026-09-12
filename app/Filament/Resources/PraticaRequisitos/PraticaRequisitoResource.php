<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitos;

use App\Filament\Unicofin\Resources\PraticaRequisitos\Pages\CreatePraticaRequisito;
use App\Filament\Unicofin\Resources\PraticaRequisitos\Pages\EditPraticaRequisito;
use App\Filament\Unicofin\Resources\PraticaRequisitos\Pages\ListPraticaRequisitos;
use App\Filament\Unicofin\Resources\PraticaRequisitos\Schemas\PraticaRequisitoForm;
use App\Filament\Unicofin\Resources\PraticaRequisitos\Tables\PraticaRequisitosTable;
use App\Models\PraticaRequisito;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PraticaRequisitoResource extends Resource
{
    protected static ?string $model = PraticaRequisito::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PraticaRequisitoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PraticaRequisitosTable::configure($table);
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
            'index' => ListPraticaRequisitos::route('/'),
            'create' => CreatePraticaRequisito::route('/create'),
            'edit' => EditPraticaRequisito::route('/{record}/edit'),
        ];
    }
}

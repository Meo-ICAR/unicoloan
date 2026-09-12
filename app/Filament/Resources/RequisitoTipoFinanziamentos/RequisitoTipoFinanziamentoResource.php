<?php

namespace App\Filament\Resources\RequisitoTipoFinanziamentos;

use App\Filament\Resources\RequisitoTipoFinanziamentos\Pages\CreateRequisitoTipoFinanziamento;
use App\Filament\Resources\RequisitoTipoFinanziamentos\Pages\EditRequisitoTipoFinanziamento;
use App\Filament\Resources\RequisitoTipoFinanziamentos\Pages\ListRequisitoTipoFinanziamentos;
use App\Filament\Resources\RequisitoTipoFinanziamentos\Schemas\RequisitoTipoFinanziamentoForm;
use App\Filament\Resources\RequisitoTipoFinanziamentos\Tables\RequisitoTipoFinanziamentosTable;
use App\Models\RequisitoTipoFinanziamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequisitoTipoFinanziamentoResource extends Resource
{
    protected static ?string $model = RequisitoTipoFinanziamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RequisitoTipoFinanziamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequisitoTipoFinanziamentosTable::configure($table);
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
            'index' => ListRequisitoTipoFinanziamentos::route('/'),
            'create' => CreateRequisitoTipoFinanziamento::route('/create'),
            'edit' => EditRequisitoTipoFinanziamento::route('/{record}/edit'),
        ];
    }
}

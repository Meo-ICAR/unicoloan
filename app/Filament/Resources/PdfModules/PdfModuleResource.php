<?php

namespace App\Filament\Resources\PdfModules;

use App\Filament\Resources\PdfModules\Pages\EditPdfModule;
use App\Filament\Resources\PdfModules\Pages\ListPdfModules;
use App\Filament\Resources\PdfModules\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\PdfModules\Schemas\PdfModuleForm;
use App\Filament\Resources\PdfModules\Tables\PdfModulesTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\PdfModule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PdfModuleResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = PdfModule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Moduli PDF';

    protected static ?string $modelLabel = 'Modulo PDF';

    protected static ?string $pluralModelLabel = 'Moduli PDF';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * I moduli nascono da `php artisan modules:sync-fields`, non dalla UI.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PdfModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PdfModulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPdfModules::route('/'),
            'edit' => EditPdfModule::route('/{record}/edit'),
        ];
    }
}

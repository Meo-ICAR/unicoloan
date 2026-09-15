<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\RelationManagers\CompanyRolesRelationManager;
use App\Filament\Resources\Companies\RelationManagers\MailAccountRelationManager;
use App\Filament\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Filament\Resources\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\RelationManagers\WebsitesRelationManager;
use App\Filament\Traits\HasPlanAccess;
use App\Models\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CompanyResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';  // Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    protected static UnitEnum|string|null $navigationGroup = 'Anagrafiche';

    protected static ?string $navigationLabel = 'Azienda';

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            CompanyRolesRelationManager::class,
            BranchesRelationManager::class,
            WebsitesRelationManager::class,
            MailAccountRelationManager::class,
        ];
    }

    // ... altre configurazioni (icona, label, ecc.) ...

    /**
     * Sovrascrive l'URL del menu di navigazione
     */
    public static function getNavigationUrl(): string
    {
        // 1. Recupera il primo record dell'azienda nel database
        $firstCompany = Company::first();

        // 2. Se l'azienda esiste, genera il link alla pagina 'view' passando il record
        if ($firstCompany) {
            return static::getUrl('edit', ['record' => $firstCompany]);
        }

        // 3. Fallback di sicurezza: se il DB è vuoto, manda alla index per evitare crash
        return static::getUrl('index');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            //  'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}

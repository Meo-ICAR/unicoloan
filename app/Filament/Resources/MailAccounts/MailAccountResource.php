<?php

namespace App\Filament\Resources\MailAccounts;

use App\Filament\Resources\MailAccounts\Pages\CreateMailAccount;
use App\Filament\Resources\MailAccounts\Pages\EditMailAccount;
use App\Filament\Resources\MailAccounts\Pages\ListMailAccounts;
use App\Filament\Resources\MailAccounts\Schemas\MailAccountForm;
use App\Filament\Resources\MailAccounts\Tables\MailAccountsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\MailAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MailAccountResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = MailAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return MailAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailAccountsTable::configure($table);
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
            'index' => ListMailAccounts::route('/'),
            'create' => CreateMailAccount::route('/create'),
            'edit' => EditMailAccount::route('/{record}/edit'),
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

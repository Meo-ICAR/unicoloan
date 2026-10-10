<?php

namespace App\Filament\Resources\ApiCalls;

use App\Filament\Resources\ApiCalls\Pages\ListApiCalls;
use App\Filament\Resources\ApiCalls\Tables\ApiCallsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ApiCall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

class ApiCallResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ApiCall::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Chiamate API';

    protected static ?string $modelLabel = 'Chiamata API';

    protected static ?string $pluralModelLabel = 'Chiamate API';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return ApiCallsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiCalls::route('/'),
        ];
    }
}

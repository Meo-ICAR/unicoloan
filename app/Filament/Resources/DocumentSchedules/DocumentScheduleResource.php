<?php

namespace App\Filament\Resources\DocumentSchedules;

use App\Filament\Resources\DocumentSchedules\Pages\ManageDocumentSchedules;
use App\Filament\Resources\DocumentSchedules\Tables\DocumentSchedulesTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\DocumentSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Scadenziario: documenti assenti, con anomalia o in scadenza, da sollecitare.
 */
class DocumentScheduleResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = DocumentSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Scadenziario';

    protected static ?string $modelLabel = 'Scadenza';

    protected static ?string $pluralModelLabel = 'Scadenze';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return DocumentSchedulesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentSchedules::route('/'),
        ];
    }
}

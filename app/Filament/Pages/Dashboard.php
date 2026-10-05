<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IstruzioniWidget;
use BackedEnum;
// use Filament\Widgets\Widget;
use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    // ...

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    /*
     * protected static ?string $navigationLabel = 'Manuale';
     *
     * protected static ?string $modelLabel = 'Manuale';
     *
     * protected static ?string $pluralModelLabel = 'Manuale';
     *
     * protected int|string|array $columnSpan = 'full';  // Larghezza piena
     *
     * // protected static UnitEnum|string|null $navigationGroup = 'Settings';
     *
     * // protected static ?int $navigationSort = 3;
     *
     * protected static ?string $recordTitleAttribute = 'Manuale';
     *
     * protected function getHeaderWidgets(): array
     * {
     *     return [
     *         // IstruzioniWidget::class
     *     ];
     * }
     */
}

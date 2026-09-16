<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Documents\DocumentResource;
// use App\Models\CompanyInspection;
use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ComplianceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Documenti scaduti', Document::where('expires_at', '<', now())->whereNotNull('expires_at')->where('is_monitored', true)->count())
                ->description('Richiedono rinnovo')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->url(DocumentResource::getUrl('index', [
                    'filters' => [
                        'expires_at' => ['expires_until' => now()->toDateString()],
                    ],
                ])),

            /*
             * Stat::make('Ispezioni nel Semestre', CompanyInspection::count())
             *     ->description('Programmate o completate')
             *     ->descriptionIcon('heroicon-m-shield-check')
             *     ->color('success'),
             */
        ];
    }
}

<?php

namespace App\Filament\Resources\DocumentSchedules\Pages;

use App\Filament\Resources\DocumentSchedules\DocumentScheduleResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

class ManageDocumentSchedules extends ManageRecords
{
    protected static string $resource = DocumentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sincronizza')
                ->label('Aggiorna scadenziario')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    Artisan::call('documents:sync-schedule');

                    Notification::make()->title('Scadenziario aggiornato')->success()->send();
                }),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('Documenti assenti, con anomalia o in scadenza. Per inviare solleciti selezionare i documenti: parte una sola email per ogni destinatario con il riepilogo.');
    }
}

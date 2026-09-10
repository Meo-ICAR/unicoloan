<?php

namespace App\Filament\Actions;

use App\Services\ImportPraticheService;
use App\ValueObjects\OamSemester;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

class ImportOamAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'importOam';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Importa Pratiche')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->modalHeading('Importa Pratiche dal Gestionale')
            ->modalDescription('ATTENZIONE! Tutte le modifiche manuali saranno rimosse. ')
            ->modalWidth('md')

            ->action(function (array $data): void {
                $semestre = OamSemester::current();

                try {
                    $count = app(ImportPraticheService::class)->import($semestre);

                    Notification::make()
                        ->title('Importazione completata')
                        ->body("Importate {$count} pratiche")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Errore durante l\'importazione')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}

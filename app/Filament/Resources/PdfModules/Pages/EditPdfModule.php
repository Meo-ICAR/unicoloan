<?php

namespace App\Filament\Resources\PdfModules\Pages;

use App\Filament\Resources\PdfModules\PdfModuleResource;
use App\Models\PdfModule;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PdfFormFiller;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditPdfModule extends EditRecord
{
    protected static string $resource = PdfModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('diagnostica')
                ->label('Genera PDF diagnostico')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function (PdfModule $record): void {
                    try {
                        $path = app(PdfFormFiller::class)->storeDiagnostic($record->load('pdfModuleFields'));
                    } catch (PdfFormException $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Compilazione non disponibile')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('PDF diagnostico pronto')
                        ->body('Ogni campo mostra il proprio nome: usalo per capire dove sta ciascun campo.')
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('apri')
                                ->label('Apri PDF')
                                ->button()
                                ->url(Storage::disk('public')->url($path), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),
        ];
    }
}

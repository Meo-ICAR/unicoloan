<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Exports\Sheets\M510AnagraficaSheet;  // Il percorso del tuo foglio specifico
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;  // Assicurati di importare il tuo modello Company
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
            Action::make('createtask')
                ->label('Crea plico')
                ->icon('heroicon-o-document-plus')
                ->form([
                    Select::make('task_id')
                        ->label('Seleziona il Task')
                        ->options(fn($record) => Task::getAvailableFor($record)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ]),
        ];
    }
}

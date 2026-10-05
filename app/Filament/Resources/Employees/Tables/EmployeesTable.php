<?php

namespace App\Filament\Resources\Employees\Tables;

// use App\Filament\Traits\CanExportTable;
use App\Filament\Exports\DynamicGroupExport;
use App\Models\Company;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
// use App\Models\Rui;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
// use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
// use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class EmployeesTable
{
    // use CanExportTable;

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make(),
                        //    ->groupBy('Produttore')  // Campo per il raggruppamento
                        //    ->sumColumns(['Provvigione']),  // Campi da sommare
                    ])
                    ->label('Esporta Excel')
                    ->color('success'),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nominativo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee_roles')
                    ->label('Ruolo')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('hiring_date')
                    ->label('Data assunzione')
                    ->date('d/m/y')
                    ->sortable(),
                TextColumn::make('oam_at')
                    ->label('Data OAM')
                    ->date('d/m/y')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Indirizzo email')
                    ->searchable(),
                TextColumn::make('termination_date')
                    ->label('Data cessazione')
                    ->date('d/m/y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('employee_types')
                    ->label('Filtra per Ruolo')
                    ->options([
                        'dipendente' => 'Dipendente',
                        'cda' => 'CdA',
                        'compliance' => 'Compliance',
                        'audit' => 'Audit',
                        'quality' => 'Quality',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                            ? $query->whereJsonContains('employee_types', $data['value'])
                            : $query
                    ),

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('createDocumentationForDipendente')
                        ->label('Aggiungi plico documentazione')
                        ->icon('heroicon-o-document-plus')
                        ->requiresConfirmation()
                        // 1. Definiamo il form all'interno del Modal della Bulk Action
                        ->form([
                            Select::make('task_id')
                                ->label('Seleziona il Task')
                                ->options(fn () => Task::where('taskable', 'employee')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        // 2. Elaboriamo l'azione recuperando i dati compilati nel form ($data)
                        ->action(function (Collection $records, array $data) {
                            // Recuperiamo l'azienda principale
                            $company = Company::first();

                            if (! $company) {
                                Notification::make()
                                    ->title('Errore')
                                    ->body('Nessuna azienda trovata nel sistema.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Recuperiamo il SINGOLO task selezionato dall'utente nel form
                            $task = Task::with('documentTypes')->find($data['task_id']);

                            if (! $task) {
                                Notification::make()
                                    ->title('Errore')
                                    ->body('Task non trovato.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $company_id = $company->id;
                            $createdCount = 0;

                            // 3. Cicliamo SOLO sui fornitori selezionati per questo specifico task
                            foreach ($records as $fornitore) {
                                $createdCount += $task->createDocumentation($company_id, $fornitore->id);
                            }

                            // 4. Notifica finale
                            if ($createdCount > 0) {
                                Notification::make()
                                    ->title('Documentazione generata')
                                    ->body("Creati con successo {$createdCount} nuovi documenti per il task \"{$task->name}\".")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Tutto aggiornato')
                                    ->body('I documenti per questo task erano già tutti presenti per i fornitori selezionati.')
                                    ->info()
                                    ->send();
                            }
                        }),
                ]),
            ]);  // toolbarActions
    }
}

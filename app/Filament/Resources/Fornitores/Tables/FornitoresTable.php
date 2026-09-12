<?php

namespace App\Filament\Unicofin\Resources\Fornitores\Tables;

use App\Filament\Exports\DynamicGroupExport;
use App\Models\Company;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;  // Importante per il form nel modal
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class FornitoresTable
{
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
                // DATI PRINCIPALI
                TextColumn::make('name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('nome')
                    ->label('Ragione Sociale')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('piva')
                    ->label('P. IVA')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('stipulated_at')
                    ->label('Mandato')
                    ->date('d/m/y')
                    ->sortable(),

                TextColumn::make('dismissed_at')
                    ->label('Cessato')
                    ->date('d/m/y')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable(),

                TextColumn::make('pec')
                    ->label('PEC')
                    ->sortable(),

                TextColumn::make('ivass')
                    ->label('IVASS')
                    ->searchable()
                    ->sortable(),

                // STATO E INQUADRAMENTO

            ])
            ->filters([
                // Filtro per stato attivo/inattivo
                Filter::make('is_active')
                    ->default(true)
                    ->label('Attivo'),
                // ->query(fn ($query) => $query->whereDate('stipulated_at', '<=', now()->subMonth(6))),
                Filter::make('dismissed_at')
                    ->default(true)
                    ->label('In essere')
                    ->query(fn ($query) => $query->whereNull('dismissed_at')),

                Filter::make('stipulated_at')
                    ->label('Mandato antecedente 6 mesi')
                    ->query(fn ($query) => $query->whereDate('stipulated_at', '<=', now()->subMonth(6))),
                // Filtro per tipologia di mandato Enasarco
                SelectFilter::make('enasarco')
                    ->label('Mandato Enasarco')
                    ->options([
                        'no' => 'Nessuno',
                        'monomandatario' => 'Monomandatario',
                        'plurimandatario' => 'Plurimandatario',
                        'societa' => 'Società',
                    ]),
                // Filtro per natura del collaboratore
                TernaryFilter::make('isdipendente')
                    ->label('Contratto')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo Dipendenti')
                    ->falseLabel('Solo P. IVA / Agenzie'),
                Filter::make('dismessed_at')
                    ->label('Cessati')
                    ->query(fn ($query) => $query->whereNotNull('dismessed_at')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('CheckPEC')
                        ->label('Invia PEC di check periodica')
                        ->icon('heroicon-o-check-badge')  // Un'icona leggermente diversa per distinguerla dal sollecito
                        ->requiresConfirmation()
                        ->color('info')
                        ->modalHeading('Invio Check PEC')
                        ->modalDescription('Sei sicuro di voler inviare un messaggio di test agli indirizzi PEC dei fornitori selezionati per verificarne la validità?')
                        ->action(function (Collection $records) {
                            $sentCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $record) {
                                // Sostituisci 'pec_email' con il vero nome del campo nel tuo database
                                // Se la PEC è su una relazione (es. $record->entity->pec), adatta di conseguenza.
                                $pecAddress = $record->pec ?? null;

                                // Salto il fornitore se non ha un indirizzo PEC salvato
                                if (empty($pecAddress)) {
                                    $skippedCount++;

                                    continue;
                                }

                                $subject = 'Verifica periodica indirizzo PEC - Non rispondere';
                                $body = "Buongiorno,\n\nla presente per verificare il corretto funzionamento e la validità del vostro indirizzo PEC presente nei nostri sistemi.\n\nVi preghiamo di ignorare questo messaggio. Non è necessario rispondere a questa email.\n\nCordiali saluti.";

                                // È consigliato usare la Facade Mail di Laravel invece della funzione nativa mail() di PHP
                                // FORZIAMO L'USO DEL MAILER 'pec' CONFIGURATO PRIMA
                                Mail::mailer('pec')->raw($body, function ($message) use ($pecAddress, $subject) {
                                    $message
                                        ->to($pecAddress)
                                        ->from(config('mail.mailers.pec.username'),
                                            config('pec.from_name', 'Tua Azienda PEC'))
                                        ->subject($subject);
                                });

                                // Opzionale ma consigliato: traccia quando hai fatto l'ultimo controllo su questo fornitore
                                // $record->update(['last_pec_check_at' => now()]);

                                $sentCount++;
                            }

                            // Costruisco un messaggio di notifica dinamico per sapere esattamente cosa è successo
                            $notificationBody = "Inviate **{$sentCount}** email di check.";
                            if ($skippedCount > 0) {
                                $notificationBody .= " Saltati **{$skippedCount}** fornitori perché sprovvisti di indirizzo PEC.";
                            }

                            Notification::make()
                                ->title('Check PEC Completato')
                                ->body($notificationBody)
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('createDocumentationForFornitori')
                        ->label('Aggiungi plico documentazione')
                        ->icon('heroicon-o-document-plus')
                        ->requiresConfirmation()
                        // 1. Definiamo il form all'interno del Modal della Bulk Action
                        ->form([
                            Select::make('task_id')
                                ->label('Seleziona il Plico Documentazione')
                                ->options(fn () => Task::where('taskable', 'fornitore')->pluck('name', 'id'))
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
            ])
            ->emptyStateHeading('Nessun agente trovato')
            ->emptyStateDescription('Crea agente per iniziare.');
    }
}

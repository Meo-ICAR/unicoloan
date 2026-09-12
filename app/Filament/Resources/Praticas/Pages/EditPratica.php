<?php

namespace App\Filament\Unicofin\Resources\Praticas\Pages;

use App\Filament\Unicofin\Resources\Praticas\PraticaResource;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Pratica;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;

class EditPratica extends EditRecord
{
    protected static string $resource = PraticaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cambia_banca')
                ->label('Cambia Banca')
                ->icon('heroicon-o-arrows-right-left') // Un'icona di scambio
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Cambio Banca e Duplicazione Pratica')
                ->modalDescription('Questa azione imposterà la pratica corrente come "CHIUSA" e ne creerà una nuova identica, ma assegnata alla nuova banca scelta.')
                ->form([
                    Select::make('nuova_banca')
                        ->label('Seleziona la Nuova Banca')
                        // Se hai una tabella Banche dedicata:
                        ->options(Clienti::where('is_active', true)->pluck('name', 'id'))

                        ->searchable()
                        ->required(),
                ])
                ->action(function ($record, array $data, Action $action) {
                    // Eseguiamo tutto in una transazione per evitare dati parziali in caso di errore
                    DB::transaction(function () use ($record, $data) {

                        // 1. Clona il record esistente (copia tutti i campi tranne l'ID)
                        $nuovaPratica = $record->replicate();

                        // 2. Modifica i dati della NUOVA pratica
                        $nuovaPratica->denominazione_banca = $data['nuova_banca'];

                        // (Opzionale ma consigliato) Resetta lo stato e la timeline della nuova pratica
                        $nuovaPratica->stato_pratica = 'inserita'; // La nuova pratica parte da capo
                        $nuovaPratica->sended_at = null;
                        $nuovaPratica->approved_at = null;
                        $nuovaPratica->erogated_at = null;
                        $nuovaPratica->rejected_at = null;

                        // Salva il record clonato nel database
                        $nuovaPratica->save();

                        // 3. Modifica e chiudi la VECCHIA pratica
                        $record->update([
                            'stato_pratica' => 'CHIUSA',
                        ]);
                    });

                    // Mostra una notifica di successo
                    Notification::make()
                        ->title('Pratica trasferita con successo')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}

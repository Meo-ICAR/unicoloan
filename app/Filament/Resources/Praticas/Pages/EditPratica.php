<?php

namespace App\Filament\Resources\Praticas\Pages;

use App\Enums\KycCoverage;
use App\Filament\Actions\GeneraModuliPraticaAction;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class EditPratica extends EditRecord
{
    protected static string $resource = PraticaResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $coverage = once(function (): ?KycCoverage {
            $client = app(ModuleDataResolver::class)->findClient($this->getRecord());

            return $client?->kycCoverage();
        });

        return match ($coverage) {
            KycCoverage::Missing => 'Attenzione: KYC mancante per il cliente',
            KycCoverage::Expired => 'Attenzione: KYC scaduto per il cliente',
            default => null,
        };
    }

    /**
     * L'annotazione non e' una colonna: viaggia sul modello fino al log del cambio di stato.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->getRecord()->pendingStatusNote = $data['status_note'] ?? null;
        unset($data['status_note']);

        return $data;
    }

    /**
     * Il riquadro dello storico deve mostrare subito la voce appena registrata.
     */
    protected function afterSave(): void
    {
        $this->getRecord()->unsetRelation('statusHistory');
    }

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
            GeneraModuliPraticaAction::make(),
            DeleteAction::make(),
        ];
    }
}

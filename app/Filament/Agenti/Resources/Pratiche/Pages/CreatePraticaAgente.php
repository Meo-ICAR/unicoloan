<?php

namespace App\Filament\Agenti\Resources\Pratiche\Pages;

use App\Filament\Agenti\Resources\Pratiche\PraticaAgenteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePraticaAgente extends CreateRecord
{
    protected static string $resource = PraticaAgenteResource::class;

    /**
     * L'agente non sceglie ne' lo stato ne' l'assegnazione: sono sempre quelli del suo fornitore.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $fornitore = PraticaAgenteResource::fornitore();

        return [
            'nome_cliente' => $data['nome_cliente'],
            'cognome_cliente' => $data['cognome_cliente'],
            'codice_fiscale' => $data['codice_fiscale'],
            'tipo_prodotto' => $data['tipo_prodotto'],
            'denominazione_banca' => $data['denominazione_banca'],
            'amount' => $data['amount'],
            'id' => (string) Str::uuid(),
            'codice_pratica' => 'AG-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'stato_pratica' => 'INSERITA',
            'data_inserimento_pratica' => today(),
            'denominazione_agente' => $fornitore?->name,
            'partita_iva_agente' => $fornitore?->piva,
            'is_notowned' => false,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PraticaAgenteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}

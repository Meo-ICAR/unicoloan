<?php

namespace App\Services;

use App\Enums\PraticaClientRole;
use App\Models\Client;
use App\Models\ClientMandate;
use App\Models\PraticaClient;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dal cliente: apre il mandato (se il prodotto lo richiede) e la prima pratica di finanziamento (stato INSERITA).
 * La pratica e' legata al cliente dal codice fiscale / P.IVA; per i prodotti diversi da cessione/delega
 * il cliente e' registrato anche come richiedente.
 */
class MandatoPraticaCreator
{
    /**
     * @param  array{tipo_prodotto: string, denominazione_banca?: ?string, amount: int|float|string, scopo_finanziamento?: ?string}  $data
     * @return array{mandate: ?ClientMandate, pratica: Pratica} il mandato e' null per i prodotti che non lo richiedono (es. utenze)
     */
    public function create(Client $client, array $data): array
    {
        return DB::connection('mysql_proforma')->transaction(function () use ($client, $data): array {
            $requiresMandate = Tipoprodotto::query()->where('name', $data['tipo_prodotto'])->value('requires_mandate') ?? true;

            $mandate = ! $requiresMandate ? null : ClientMandate::create([
                'client_id' => $client->getKey(),
                'numero_mandato' => ClientMandate::nextNumber(),
                'data_firma_mandato' => today(),
                'data_scadenza_mandato' => today()->addYears(2),
                'data_consegna_trasparenza' => today(),
                'importo_richiesto_mandato' => $data['amount'],
                'scopo_finanziamento' => $data['scopo_finanziamento'] ?? null,
                'stato' => 'attivo',
            ]);

            $pratica = Pratica::create([
                'id' => (string) Str::uuid(),
                'codice_pratica' => 'PR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'nome_cliente' => $client->is_person ? $client->first_name : null,
                'cognome_cliente' => $client->name,
                'codice_fiscale' => $client->tax_code,
                'tipo_prodotto' => $data['tipo_prodotto'],
                'denominazione_banca' => $data['denominazione_banca'] ?? null,
                'amount' => $data['amount'],
                'stato_pratica' => 'INSERITA',
                'data_inserimento_pratica' => today(),
                'is_notowned' => false,
            ]);

            if (! $pratica->isCessioneDelega()) {
                PraticaClient::create([
                    'pratica_id' => $pratica->getKey(),
                    'client_id' => $client->getKey(),
                    'role' => PraticaClientRole::Applicant,
                ]);
            }

            return ['mandate' => $mandate, 'pratica' => $pratica];
        });
    }
}

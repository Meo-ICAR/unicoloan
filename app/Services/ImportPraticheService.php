<?php

namespace App\Services;

use App\Models\OamPratiche;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\PROFORMA\Provvigione;
use App\ValueObjects\OamSemester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImportPraticheService
{
    /**
     * Importa le pratiche del semestre da PROFORMA nella tabella di lavoro
     * oam_pratiches e ricostruisce l'aggregato semestrale.
     *
     * L'operazione:
     * - e' protetta da un lock atomico (niente doppie esecuzioni concorrenti);
     * - cancella solo i dati della company + periodo correnti, non l'intera
     *   tabella, per non perdere lo storico degli altri periodi;
     * - gira in un'unica transazione: in caso di errore non lascia la tabella
     *   in stato incoerente.
     *
     * @return int Numero di pratiche importate
     */
    public function import(?OamSemester $semester = null): int
    {
        $semester ??= OamSemester::current();

        $lock = Cache::lock('oam:import-pratiche', 900);

        if (! $lock->get()) {
            throw new \RuntimeException('Un import OAM e\' gia\' in corso. Riprovare tra qualche minuto.');
        }

        try {
            return $this->runImport($semester);
        } finally {
            $lock->release();
        }
    }

    private function runImport(OamSemester $semester): int
    {
        $companyId = app(CompanyResolver::class)->resolveId();
        $period = $semester->period();

        return DB::transaction(function () use ($semester, $companyId, $period): int {
            // Rimuoviamo solo il periodo/azienda in ricostruzione.
            OamPratiche::query()
                ->where('company_id', $companyId)
                ->where('period', $period)
                ->forceDelete();

            $importedCount = 0;

            Pratica::perSemestreOam($semester)
                ->chunkById(1000, function (Collection $pratiche) use (&$importedCount, $companyId, $period): void {
                    $provvigioni = $this->loadProvvigioniAggregate(
                        $pratiche->pluck('codice_pratica')->filter()->all()
                    );

                    foreach ($pratiche as $pratica) {
                        $this->importSingle($pratica, $companyId, $period, $provvigioni[$pratica->codice_pratica] ?? null);
                        $importedCount++;
                    }
                });

            $this->importStorni($semester, $companyId, $period);
            $this->applyBusinessRules($period, $companyId);

            app(OamSemestraleService::class)->aggregate(period: $period, companyId: $companyId);

            return $importedCount;
        });
    }

    /**
     * Somma delle provvigioni per codice pratica, in un'unica query invece di
     * 5-6 query per ogni pratica.
     *
     * @param  array<int, string>  $codiciPratica
     * @return array<string, \stdClass>
     */
    private function loadProvvigioniAggregate(array $codiciPratica): array
    {
        if ($codiciPratica === []) {
            return [];
        }

        return Provvigione::query()
            ->whereIn('id_pratica', $codiciPratica)
            ->selectRaw('id_pratica')
            ->selectRaw("SUM(CASE WHEN tipo = 'Cliente' THEN importo ELSE 0 END) as provv_clientela")
            ->selectRaw("SUM(CASE WHEN tipo = 'Istituto' AND descrizione NOT LIKE '%premio%' THEN importo ELSE 0 END) as provv_istituto_comp")
            ->selectRaw("SUM(CASE WHEN tipo = 'Istituto' AND descrizione LIKE '%premio%' THEN importo ELSE 0 END) as premi_istituto_comp")
            ->selectRaw("SUM(CASE WHEN tipo = 'Agente' THEN importo ELSE 0 END) as payout_rete_credito")
            ->selectRaw("SUM(CASE WHEN tipo = 'Istituto' AND descrizione LIKE '%storno%' THEN importo ELSE 0 END) as importo_retrocesse")
            ->groupBy('id_pratica')
            ->get()
            ->keyBy('id_pratica')
            ->all();
    }

    private function importSingle(Pratica $pratica, string $companyId, string $period, ?\stdClass $provvigioni): OamPratiche
    {
        $istitutoNome = $pratica->denominazione_banca;
        $istitutoCanonico = Clienti::getClienteNomeByName($istitutoNome);
        $cliente = Clienti::query()->where('name', $istitutoNome)->first();

        $tipoProdotto = $pratica->tipo_prodotto;
        $erogato = $this->erogatoLordo($pratica, $cliente?->principal_type, $tipoProdotto);
        $storno = (float) ($provvigioni->importo_retrocesse ?? 0.0);

        return OamPratiche::updateOrCreate(
            ['pratica' => $pratica->codice_pratica],
            [
                'company_id' => $companyId,
                'period' => $period,
                'istituto' => $istitutoCanonico,
                'intermediari_non_convenzionati' => blank($istitutoCanonico) ? 1 : 0,
                'agente' => Fornitore::getFornitoreNomeByName($pratica->denominazione_agente),
                'cliente' => $this->clienteLabel($pratica),
                'tipo_prodotto' => $tipoProdotto,
                'erogato_lordo' => $erogato,
                'sended_at' => $this->sendedAt($pratica),
                'approved_at' => $this->approvedAt($pratica),
                'erogated_at' => $pratica->erogated_at,
                'rejected_at' => $pratica->rejected_at,
                'provv_clientela' => (float) ($provvigioni->provv_clientela ?? 0.0),
                'provv_istituto_comp' => (float) ($provvigioni->provv_istituto_comp ?? 0.0),
                'premi_istituto_comp' => (float) ($provvigioni->premi_istituto_comp ?? 0.0),
                'payout_rete_credito' => (float) ($provvigioni->payout_rete_credito ?? 0.0),
                'importo_retrocesse' => $storno,
                'num_rivalse' => abs($storno) > 0 ? 1 : 0,
                'abi_name' => $this->abiName($pratica, $cliente?->abi_name, $istitutoNome),
            ]
        );
    }

    private function importStorni(OamSemester $semester, string $companyId, string $period): void
    {
        Provvigione::storniOam($semester)
            ->whereHas('pratica', function ($q) use ($semester): void {
                // Storni di pratiche erogate PRIMA del semestre in analisi.
                $q->where('erogated_at', '<', $semester->start);
            })
            ->with(['pratica:id,erogated_at,codice_pratica,denominazione_banca,denominazione_agente,tipo_prodotto,abi_name,net,nome_cliente,cognome_cliente'])
            ->get()
            ->each(function (Provvigione $provvigione) use ($companyId, $period): void {
                if ($provvigione->pratica === null) {
                    return;
                }

                $this->importStorno($provvigione->pratica, (float) $provvigione->importo, $companyId, $period);
            });
    }

    private function importStorno(Pratica $pratica, float $storno, string $companyId, string $period): OamPratiche
    {
        $istitutoNome = $pratica->denominazione_banca;
        $istitutoCanonico = Clienti::getClienteNomeByName($istitutoNome);

        return OamPratiche::updateOrCreate(
            ['pratica' => $pratica->codice_pratica],
            [
                'company_id' => $companyId,
                'period' => $period,
                'istituto' => $istitutoCanonico,
                'intermediari_non_convenzionati' => blank($istitutoCanonico) ? 1 : 0,
                'agente' => $pratica->denominazione_agente,
                'cliente' => $this->clienteLabel($pratica),
                'tipo_prodotto' => $pratica->tipo_prodotto,
                'erogato_lordo' => 0,
                'sended_at' => $this->sendedAt($pratica),
                'approved_at' => $this->approvedAt($pratica),
                'erogated_at' => $pratica->erogated_at,
                'rejected_at' => $pratica->rejected_at,
                'provv_clientela' => 0,
                'provv_istituto_comp' => 0,
                'premi_istituto_comp' => 0,
                'payout_rete_credito' => 0,
                'importo_retrocesse' => $storno,
                'num_rivalse' => 1,
                'abi_name' => $pratica->abi_name,
            ]
        );
    }

    /**
     * Regole di business post-import (classificazione prodotto creditizio,
     * separazione erogato lavorazione / erogato lordo). Ristrette al
     * periodo/azienda in ricostruzione.
     */
    private function applyBusinessRules(string $period, string $companyId): void
    {
        DB::update(
            'UPDATE oam_pratiches o
             INNER JOIN oam_codes c ON c.tipo_prodotto = o.tipo_prodotto
             SET o.prodotto_creditizio = c.description,
                 o.pratiche_lavorazione = IF(o.erogated_at IS NULL, 1, 0),
                 o.pratiche_intermediate = IF(o.erogated_at IS NOT NULL, 1, 0)
             WHERE o.period = ? AND o.company_id = ?',
            [$period, $companyId]
        );

        DB::update(
            "UPDATE oam_pratiches o
             SET o.prodotto_creditizio = 'Segnalazione Mutuo'
             WHERE o.tipo_prodotto = 'Mutuo' AND o.erogato = 0
               AND o.period = ? AND o.company_id = ?",
            [$period, $companyId]
        );

        DB::update(
            "UPDATE oam_pratiches o
             SET o.prodotto_creditizio = 'Segnalazione Finanziamento'
             WHERE o.tipo_prodotto <> 'Mutuo' AND o.erogato = 0
               AND o.period = ? AND o.company_id = ?",
            [$period, $companyId]
        );

        DB::update(
            'UPDATE oam_pratiches o
             SET o.erogato_lavorazione = o.erogato_lordo, o.erogato_lordo = 0
             WHERE o.pratiche_lavorazione = 1
               AND o.period = ? AND o.company_id = ?',
            [$period, $companyId]
        );
    }

    private function erogatoLordo(Pratica $pratica, ?string $principalType, ?string $tipoProdotto): float
    {
        if ($principalType !== 'banca') {
            return 0.0;
        }

        return in_array($tipoProdotto, ['Cessione', 'Delega'], true)
            ? (float) $pratica->amount
            : (float) $pratica->net;
    }

    private function abiName(Pratica $pratica, ?string $clienteAbiName, ?string $istitutoNome): ?string
    {
        foreach ([$pratica->abi_name, $clienteAbiName, $istitutoNome] as $candidate) {
            if (filled($candidate) && ! str_starts_with((string) $candidate, '-')) {
                return $candidate;
            }
        }

        return $istitutoNome;
    }

    private function clienteLabel(Pratica $pratica): ?string
    {
        $label = trim(($pratica->nome_cliente ?? '').' '.($pratica->cognome_cliente ?? ''));

        return $label !== '' ? $label : null;
    }

    private function sendedAt(Pratica $pratica): mixed
    {
        return $pratica->sended_at ?? $this->approvedAt($pratica);
    }

    private function approvedAt(Pratica $pratica): mixed
    {
        return $pratica->approved_at ?? $pratica->erogated_at;
    }
}

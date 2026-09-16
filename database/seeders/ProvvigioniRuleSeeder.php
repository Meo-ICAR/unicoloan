<?php

namespace Database\Seeders;

use App\Models\ProvvigioniRule;
use Illuminate\Database\Seeder;

class ProvvigioniRuleSeeder extends Seeder
{
    /**
     * Popola le regole provvigionali di rete di default (applicabili a tutte
     * le banche/agenti, `clienti_id`/`fornitori_id`/`kind_id` null), una per
     * ciascun tipo di prodotto seminato da TipoProdottoSeeder. Regole
     * specifiche per singola banca o agente vanno inserite a parte tramite
     * il pannello, non da questo seeder. Non sovrascrive righe già esistenti
     * che rispettano gli stessi criteri.
     */
    public function run(): void
    {
        $regole = [
            ['tipoprodotto_id' => 5, 'tipo_provvigioni' => 'lordo', 'value' => 3.0000],
            ['tipoprodotto_id' => 7, 'tipo_provvigioni' => 'erogato', 'value' => 4.5000],
            ['tipoprodotto_id' => 9, 'tipo_provvigioni' => 'erogato', 'value' => 4.0000],
            ['tipoprodotto_id' => 11, 'tipo_provvigioni' => 'lordo', 'value' => 2.5000],
            ['tipoprodotto_id' => 12, 'tipo_provvigioni' => 'erogato', 'value' => 5.0000],
            ['tipoprodotto_id' => 13, 'tipo_provvigioni' => 'erogato', 'value' => 1.2000],
            ['tipoprodotto_id' => 15, 'tipo_provvigioni' => 'lordo', 'value' => 15.0000],
            ['tipoprodotto_id' => 16, 'tipo_provvigioni' => 'erogato', 'value' => 6.0000],
            ['tipoprodotto_id' => 17, 'tipo_provvigioni' => 'lordo', 'value' => 3.5000],
            ['tipoprodotto_id' => 18, 'tipo_provvigioni' => 'erogato', 'value' => 2.0000],
        ];

        foreach ($regole as $regola) {
            ProvvigioniRule::firstOrCreate(
                [
                    'tipoprodotto_id' => $regola['tipoprodotto_id'],
                    'tipoprodotto_sub_id' => null,
                    'clienti_id' => null,
                    'kind_id' => null,
                    'fornitori_id' => null,
                ],
                [
                    'coordinamento' => false,
                    'iscliente' => false,
                    'tipo_provvigioni' => $regola['tipo_provvigioni'],
                    'value' => $regola['value'],
                    'valid_from' => now()->startOfYear(),
                    'notes' => 'Regola di rete di default, applicabile in assenza di regole più specifiche.',
                ]
            );
        }
    }
}

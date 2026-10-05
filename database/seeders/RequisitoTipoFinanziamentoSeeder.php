<?php

namespace Database\Seeders;

use App\Models\PraticaRequisito;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequisitoTipoFinanziamentoSeeder extends Seeder
{
    public function run(): void
    {
        $reqMap = PraticaRequisito::pluck('id', 'codice')->toArray();

        $regole = [
            // Cessione del Quinto Stipendio (Sub ID: 1)
            1 => [
                ['requisito' => 'certificato_stipendio', 'obbligatorio' => true,  'ordine' => 10],
                ['requisito' => 'polizza_vita',          'obbligatorio' => true,  'ordine' => 20],
                ['requisito' => 'polizza_impiego',       'obbligatorio' => true,  'ordine' => 30],
                ['requisito' => 'atto_benestare',        'obbligatorio' => true,  'ordine' => 40],
            ],

            // Cessione del Quinto Pensione (Sub ID: 2)
            2 => [
                ['requisito' => 'quota_cedibile',        'obbligatorio' => true,  'ordine' => 10],
                ['requisito' => 'polizza_vita',          'obbligatorio' => true,  'ordine' => 20],
            ],

            // Delega di Pagamento (Sub ID: 3)
            3 => [
                ['requisito' => 'certificato_stipendio', 'obbligatorio' => true,  'ordine' => 10],
                ['requisito' => 'polizza_vita',          'obbligatorio' => true,  'ordine' => 20],
                ['requisito' => 'polizza_impiego',       'obbligatorio' => true,  'ordine' => 30],
                ['requisito' => 'atto_benestare',        'obbligatorio' => true,  'ordine' => 40],
            ],

            // Mutuo Acquisto (Sub ID: 4)
            4 => [
                ['requisito' => 'perizia_immobile',      'obbligatorio' => true,  'ordine' => 10],
                ['requisito' => 'relazione_notarile',    'obbligatorio' => true,  'ordine' => 20],
                ['requisito' => 'polizza_incendio',      'obbligatorio' => true,  'ordine' => 30],
            ],

            // Finanziamento Chirografario Imprese (Sub ID: 12)
            12 => [
                ['requisito' => 'garanzia_mcc',          'obbligatorio' => false, 'ordine' => 10],
            ],

            // Anticipo TFS (Sub ID: 17)
            17 => [
                ['requisito' => 'quantificazione_tfs',   'obbligatorio' => true,  'ordine' => 10],
            ],
        ];

        foreach ($regole as $subId => $listaRequisiti) {
            foreach ($listaRequisiti as $item) {
                $requisitoId = $reqMap[$item['requisito']] ?? null;

                if ($requisitoId) {
                    // SOSTITUITO updateOrCreate CON updateOrInsert
                    DB::table('requisito_tipo_finanziamento')->updateOrInsert(
                        [
                            'tipoprodotto_sub_id' => $subId,
                            'pratica_requisito_id' => $requisitoId,
                        ],
                        [
                            'tipoprodotto_id' => null,
                            'obbligatorio' => $item['obbligatorio'],
                            'ordine' => $item['ordine'],
                        ]
                    );
                }
            }
        }
    }
}

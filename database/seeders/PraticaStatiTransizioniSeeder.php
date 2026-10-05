<?php

namespace Database\Seeders;

use App\Models\PraticaStato;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PraticaStatiTransizioniSeeder extends Seeder
{
    public function run(): void
    {
        $statiMap = PraticaStato::pluck('id', 'codice')->toArray();

        $transizioni = [
            'bozza' => [
                'inserita',
                'inserito',
            ],
            'inserita' => [
                'accettato_preventivo',
                'richiesta_istruttoria',
                'rinuncia_cliente',
                'pratica_respinta',
            ],
            'richiesta_istruttoria' => [
                'invio_in_istruttoria',
                'sospesa_istruttoria_interna',
                'declinata',
            ],
            'invio_in_istruttoria' => [
                'caricata_banca',
                'sospesa',
                'in_attesa_documenti_originali',
            ],
            'caricata_banca' => [
                'fascicolo_completo',
                'sospesa',
                'declinata',
            ],
            'fascicolo_completo' => [
                'richiesta_polizza',
                'approvata',
                'deliberata',
                'declinata',
            ],
            'richiesta_polizza' => [
                'rientro_polizza',
                'declinata',
            ],
            'deliberata' => [
                'richiesta_emissione',
                'notifica',
                'rinuncia_cliente',
            ],
            'notifica' => [
                'rientro_benestare',
                'atto_fissato',
            ],
            'atto_fissato' => [
                'perfezionata',
                'liquidata',
                'rinuncia_cliente',
            ],
            'liquidata' => [
                'fatturato',
                'chiusa',
                'in_ammortamento',
            ],
            'in_ammortamento' => [
                'rinnovabile',
                'estinto',
            ],
        ];

        foreach ($transizioni as $codiceDa => $destinazioni) {
            $idDa = $statiMap[$codiceDa] ?? null;

            if ($idDa) {
                foreach ($destinazioni as $codiceA) {
                    $idA = $statiMap[$codiceA] ?? null;

                    if ($idA) {
                        // SOSTITUITO updateOrCreate CON updateOrInsert
                        DB::table('pratica_stati_transizioni')->updateOrInsert([
                            'stato_da_id' => $idDa,
                            'stato_a_id' => $idA,
                        ]);
                    }
                }
            }
        }
    }
}

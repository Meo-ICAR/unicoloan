<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProdottoSubSeeder extends Seeder
{
    /**
     * Popola i sottotipi di prodotto. Non sovrascrive né elimina righe già
     * esistenti: ogni sottotipo viene identificato dal proprio `code`
     * (univoco) e inserito solo se non già presente.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $subProdotti = [
            // Cessione (tipoprodotto_id: 7)
            ['tipoprodotto_id' => 7, 'name' => 'Cessione del Quinto Stipendio', 'code' => 'CQS', 'vincoli' => 'Solo dipendenti (pubblici/privati)'],
            ['tipoprodotto_id' => 7, 'name' => 'Cessione del Quinto Pensione', 'code' => 'CQP', 'vincoli' => 'Solo pensionati (INPS/Ex INPDAP)'],

            // Delega (tipoprodotto_id: 9)
            ['tipoprodotto_id' => 9, 'name' => 'Delega di Pagamento', 'code' => 'DP', 'vincoli' => 'Richiede convenzione aziendale attiva'],

            // Mutuo (tipoprodotto_id: 13)
            ['tipoprodotto_id' => 13, 'name' => 'Mutuo Acquisto', 'code' => 'MUT_ACQ', 'vincoli' => 'LTV massimo standard 80%'],
            ['tipoprodotto_id' => 13, 'name' => 'Mutuo Surroga', 'code' => 'MUT_SUR', 'vincoli' => 'Importo residuo minimo richiesto'],
            ['tipoprodotto_id' => 13, 'name' => 'Mutuo Liquidità', 'code' => 'MUT_LIQ', 'vincoli' => 'Ipoteca su immobile libero da vincoli'],
            ['tipoprodotto_id' => 13, 'name' => 'Mutuo Ristrutturazione', 'code' => 'MUT_RIS', 'vincoli' => 'Erogazione a SAL (Stato Avanzamento Lavori)'],

            // Prestito (tipoprodotto_id: 16)
            ['tipoprodotto_id' => 16, 'name' => 'Prestito Personale', 'code' => 'PP', 'vincoli' => 'Senza destinazione d\'uso'],
            ['tipoprodotto_id' => 16, 'name' => 'Consolidamento Debiti', 'code' => 'PP_CON', 'vincoli' => 'Estinzione prestiti in corso'],
            ['tipoprodotto_id' => 16, 'name' => 'Prestito Finalizzato', 'code' => 'PP_FIN', 'vincoli' => 'Erogazione diretta al dealer/convenzionato'],

            // Aziendale / Prestito Aziendale (tipoprodotto_id: 5, 17)
            ['tipoprodotto_id' => 17, 'name' => 'Anticipo Fatture', 'code' => 'AZ_ANT', 'vincoli' => 'Canalizzazione dei pagamenti'],
            ['tipoprodotto_id' => 17, 'name' => 'Finanziamento Chirografario', 'code' => 'AZ_CHI', 'vincoli' => 'Spesso richiede garanzia MCC'],

            // Leasing (tipoprodotto_id: 11)
            ['tipoprodotto_id' => 11, 'name' => 'Leasing Targato', 'code' => 'LEA_AUTO', 'vincoli' => 'Auto e veicoli commerciali'],
            ['tipoprodotto_id' => 11, 'name' => 'Leasing Strumentale', 'code' => 'LEA_STR', 'vincoli' => 'Beni strumentali all\'attività d\'impresa'],

            // Polizza (tipoprodotto_id: 15)
            ['tipoprodotto_id' => 15, 'name' => 'Polizza CPI', 'code' => 'POL_CPI', 'vincoli' => 'Credit Protection Insurance (legata a prestito)'],
            ['tipoprodotto_id' => 15, 'name' => 'Polizza TCM', 'code' => 'POL_TCM', 'vincoli' => 'Temporanea Caso Morte'],

            // TFS (tipoprodotto_id: 18)
            ['tipoprodotto_id' => 18, 'name' => 'Anticipo TFS', 'code' => 'TFS_ANT', 'vincoli' => 'Solo pensionati pubblici/statali con certificato quantificazione'],

            // Microcredito (tipoprodotto_id: 12)
            ['tipoprodotto_id' => 12, 'name' => 'Microcredito Imprese', 'code' => 'MIC_IMP', 'vincoli' => 'Startup o imprese con meno di 5 anni'],
        ];

        foreach ($subProdotti as $subProdotto) {
            if (DB::table('proforma.tipoprodotto_sub')->where('code', $subProdotto['code'])->exists()) {
                continue;
            }

            $subProdotto['created_at'] = $now;
            $subProdotto['updated_at'] = $now;

            DB::table('proforma.tipoprodotto_sub')->insert($subProdotto);
        }
    }
}

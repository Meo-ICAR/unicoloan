<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProdottoSeeder extends Seeder
{
    /**
     * Popola i tipi di prodotto finanziario (macro-categorie) trattati dalle
     * banche mandanti (Clienti). Gli ID sono fissi perché già referenziati da
     * TipoProdottoSubSeeder e TipoProdottoSubConstraintsSeeder tramite
     * tipoprodotto_id. Non sovrascrive righe già esistenti con lo stesso ID.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $tipiProdotto = [
            ['id' => 5, 'name' => 'Finanziamento Aziendale', 'tipo_prodotto' => 'Aziendale', 'code' => 'AZI', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Lordo'],
            ['id' => 7, 'name' => 'Cessione del Quinto', 'tipo_prodotto' => 'Cessione', 'code' => 'CQ', 'is_external' => false, 'is_oneclient' => true, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
            ['id' => 9, 'name' => 'Delega di Pagamento', 'tipo_prodotto' => 'Delega', 'code' => 'DEL', 'is_external' => false, 'is_oneclient' => true, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
            ['id' => 11, 'name' => 'Leasing', 'tipo_prodotto' => 'Leasing', 'code' => 'LEA', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Lordo'],
            ['id' => 12, 'name' => 'Microcredito', 'tipo_prodotto' => 'Microcredito', 'code' => 'MIC', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
            ['id' => 13, 'name' => 'Mutuo', 'tipo_prodotto' => 'Mutuo', 'code' => 'MUT', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
            ['id' => 15, 'name' => 'Polizza Assicurativa', 'tipo_prodotto' => 'Polizza', 'code' => 'POL', 'is_external' => true, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Lordo'],
            ['id' => 16, 'name' => 'Prestito Personale', 'tipo_prodotto' => 'Prestito', 'code' => 'PP', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
            ['id' => 17, 'name' => 'Prestito Aziendale', 'tipo_prodotto' => 'Aziendale', 'code' => 'PP_AZI', 'is_external' => false, 'is_oneclient' => false, 'oam' => null, 'tipo_provvigioni' => 'Lordo'],
            ['id' => 18, 'name' => 'TFS - Trattamento Fine Servizio', 'tipo_prodotto' => 'TFS', 'code' => 'TFS', 'is_external' => false, 'is_oneclient' => true, 'oam' => null, 'tipo_provvigioni' => 'Erogato'],
        ];

        foreach ($tipiProdotto as $tipoProdotto) {
            $table = DB::connection('mysql_proforma')->table('tipoprodotto');

            if ($table->where('id', $tipoProdotto['id'])->exists()) {
                continue;
            }

            $tipoProdotto['created_at'] = $now;
            $tipoProdotto['updated_at'] = $now;
            $tipoProdotto['is_active'] = true;

            DB::connection('mysql_proforma')->table('tipoprodotto')->insert($tipoProdotto);
        }
    }
}

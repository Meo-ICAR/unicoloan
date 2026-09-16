<?php

namespace Database\Seeders;

use App\Models\Compenso;
use Illuminate\Database\Seeder;

class CompensoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $compensi = [
            ['status_compenso' => '', 'isperfezionato' => 0],
            ['status_compenso' => 'Pratica declinata/ritirata', 'isperfezionato' => 0],
            ['status_compenso' => 'Pratica deliberata', 'isperfezionato' => 0],
            ['status_compenso' => 'Pratica erogata', 'isperfezionato' => 1],
            ['status_compenso' => 'Pratica in lavorazione', 'isperfezionato' => 0],
            ['status_compenso' => 'Pratica perfezionata', 'isperfezionato' => 0],
            ['status_compenso' => 'Pratica stornata', 'isperfezionato' => 0],
        ];

        foreach ($compensi as $compenso) {
            Compenso::updateOrCreate(
                ['status_compenso' => $compenso['status_compenso']],
                ['isperfezionato' => $compenso['isperfezionato']]
            );
        }
    }
}

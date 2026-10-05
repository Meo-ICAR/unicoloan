<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanyRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company_id = '45d36df8-369f-40ce-b4fd-b5907c342fe9';  // ID della Races Finance
        $datiFittizi = [
            [
                'company_id' => $company_id,
                'name' => 'Controllo Semestrale',
                'funzione' => 'compliance',
                'is_external' => true,
                'dal' => '2026-01-01',
                'al' => '2026-06-30',
                'execution_method' => 'documentale',
                'expertName' => 'Michele Ferri',
                'n' => 1,
            ],
            [
                'company_id' => $company_id,
                'name' => 'Controllo Semestrale',
                'funzione' => 'compliance',
                'is_external' => true,
                'dal' => '2026-01-01',
                'al' => '2026-06-30',
                'execution_method' => 'onsite',
                'expertName' => 'Michele Ferri',
                'n' => 0,
            ],
            [
                'company_id' => $company_id,  // Sostituisci con un UUID valido da 'companies'
                'name' => 'Audit Esterno',
                'funzione' => 'internal audit',
                'is_external' => true,
                'dal' => '2026-07-01',
                'al' => '2026-07-15',
                'execution_method' => 'onsite',
                'expertName' => 'Michele Ferri',
                'n' => 2,
            ],
            [
                'company_id' => $company_id,  // Sostituisci con un UUID valido da 'companies'
                'name' => 'Verifica Antiriciclaggio',
                'funzione' => 'aml',
                'is_external' => false,
                'dal' => null,
                'al' => null,
                'execution_method' => '?',  // Accetta stringa vuota come da SQL
                'expertName' => 'Luca Verdi',
                'n' => 3,
            ]
        ];

        foreach ($datiFittizi as $dato) {
            CompanyRole::create($dato);
        }
    }
}

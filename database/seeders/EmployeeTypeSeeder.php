<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Esegue il popolamento del database.
     */
    public function run(): void
    {
        $employeeTypes = [
            ['id' => 1,  'name' => 'dipendente',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 2,  'name' => 'cda',            'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 3,  'name' => 'istruttore',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 4,  'name' => 'SOS',            'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 5,  'name' => 'audit',          'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 6,  'name' => 'compliance',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 7,  'name' => 'segretaria',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 8,  'name' => 'amministrativo',  'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 9,  'name' => 'commerciale',    'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 10, 'name' => 'qualita',        'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
        ];

        foreach ($employeeTypes as $type) {
            EmployeeType::updateOrCreate(
                ['id' => $type['id']],
                $type
            );
        }
    }
}

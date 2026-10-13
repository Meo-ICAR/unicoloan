<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::query()->firstOrCreate(
            ['vat_number' => '05822361007'],
            [
                'name' => 'Races Finance S.r.l.',
                'vat_name' => 'Races Finance',
                'oam' => 'M510',
                'oam_at' => '2012-11-26',
                'oam_name' => 'RACES FINANCE SRL',
                'numero_iscrizione_rui' => 'E000689226',
                'ivass' => null,
                'ivass_at' => null,
                'ivass_name' => null,
                'ivass_section' => null,
                'sponsor' => null,
                'company_type' => 'mediatore',
                'page_header' => '<p></p>',
                'page_footer' => '<p></p>',
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('companies')->insert([
            'id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Races Finance S.r.l.',
            'vat_number' => '05822361007',
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
            'created_at' => '2026-04-21 13:26:48',
            'updated_at' => '2026-04-21 13:26:48',
        ]);
    }
}

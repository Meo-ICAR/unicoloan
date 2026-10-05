<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebsiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('websites')->insert([
            'company_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',  // Legato a Races Finance
            'websiteable_type' => 'company',
            'websiteable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Races Finance',
            'type' => 'istituzionale',
            'clienti_id' => 123,
            'is_active' => 1,
            'domain' => 'https://www.races.it',
            'is_typical' => 1,
            'privacy_date' => '2025-12-30',
            'privacy_prior_date' => '2025-01-10',
            'transparency_prior_date' => '2025-01-10',
            'url_privacy' => 'https://www.races.it/privacy-policy',
            'url_cookies' => 'https://www.races.it/cookie-policy',
            'is_footercompilant' => 1,
            'url_transparency' => 'https://www.races.it/trasparenza',
            'transparency_date' => '2026-01-15',
            'is_iso27001_certified' => 0,
            // Esempio polimorfico: agganciato direttamente alla stessa Company (che usa UUID)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('websites')->insert([
            'company_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',  // Legato a Races Finance
            'websiteable_type' => 'company',
            'websiteable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Facebook',
            'type' => 'social',
            'is_active' => 1,
            'domain' => 'https://www.facebook.com/share/1998SfyBSn/?mibextid=wwXIfr',
            'is_typical' => 0,
            'is_footercompilant' => 1,
            'is_iso27001_certified' => 0,
            // Esempio polimorfico: agganciato direttamente alla stessa Company (che usa UUID)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('websites')->insert([
            'company_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',  // Legato a Races Finance
            'websiteable_type' => 'company',
            'websiteable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Linkedin',
            'type' => 'social',
            'clienti_id' => 123,
            'is_active' => 1,
            'domain' => 'https://www.linkedin.com/company/racesxte',
            'is_typical' => 1,
            'is_footercompilant' => 1,
            'is_iso27001_certified' => 0,
            // Esempio polimorfico: agganciato direttamente alla stessa Company (che usa UUID)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('websites')->insert([
            'company_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',  // Legato a Races Finance
            'websiteable_type' => 'company',
            'websiteable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Instagram',
            'type' => 'social',
            'clienti_id' => 123,
            'is_active' => 1,
            'domain' => 'https://www.instagram.com/races.it?igsh=MnVtMzFyZDNseDM3',
            'is_typical' => 0,
            'is_footercompilant' => 1,
            'is_iso27001_certified' => 0,
            // Esempio polimorfico: agganciato direttamente alla stessa Company (che usa UUID)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('websites')->insert([
            'company_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',  // Legato a Races Finance
            'websiteable_type' => 'company',
            'websiteable_id' => '45d36df8-369f-40ce-b4fd-b5907c342fe9',
            'name' => 'Youtube',
            'type' => 'social',
            'clienti_id' => 123,
            'is_active' => 1,
            'domain' => 'https://www.instagram.com/races.it?igsh=MnVtMzFyZDNseDM3',
            'is_typical' => 1,
            'is_footercompilant' => 1,
            'is_iso27001_certified' => 0,
            // Esempio polimorfico: agganciato direttamente alla stessa Company (che usa UUID)
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}

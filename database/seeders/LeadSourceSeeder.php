<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

class LeadSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sorgenti = [
            ['name' => 'Call Center Interno', 'type' => 'call_center', 'description' => 'Contatti generati dal call center interno.'],
            ['name' => 'Sito Web - Modulo Contatti', 'type' => 'website', 'description' => 'Richieste dal form di contatto del sito.', 'utm_source' => 'website', 'utm_campaign' => 'form_contatti'],
            ['name' => 'Cliente Storico', 'type' => 'old_client', 'description' => 'Riattivazione di un cliente già acquisito in passato.'],
            ['name' => 'Facebook Ads', 'type' => 'social_media', 'description' => 'Campagne a pagamento su Facebook/Instagram.', 'utm_source' => 'facebook', 'utm_campaign' => 'lead_generation'],
            ['name' => 'Google Ads', 'type' => 'social_media', 'description' => 'Campagne a pagamento su Google Search.', 'utm_source' => 'google', 'utm_campaign' => 'search_brand'],
            ['name' => 'Passaparola / Segnalazione', 'type' => 'referral', 'description' => 'Cliente segnalato da un altro cliente o collaboratore.'],
            ['name' => 'Evento / Fiera', 'type' => 'other', 'description' => 'Contatti raccolti durante eventi o fiere di settore.'],
        ];

        foreach ($sorgenti as $sorgente) {
            LeadSource::updateOrCreate(
                ['name' => $sorgente['name']],
                $sorgente + ['is_active' => true]
            );
        }
    }
}

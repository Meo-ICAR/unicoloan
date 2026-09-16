<?php

namespace Database\Seeders;

use App\Models\ClientType;
use Illuminate\Database\Seeder;

class ClientTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipi = [
            [
                'name' => 'Cliente Persona Fisica',
                'is_person' => true,
                'is_company' => false,
                'privacy_role' => 'Interessato',
                'purpose' => 'Erogazione di servizi di mediazione creditizia',
                'data_subjects' => 'Clienti persone fisiche',
                'data_categories' => 'Dati anagrafici, reddituali, finanziari',
                'retention_period' => '10 anni dalla cessazione del rapporto',
                'extra_eu_transfer' => 'Nessuno',
                'security_measures' => 'Accesso riservato, cifratura a riposo',
            ],
            [
                'name' => 'Cliente Persona Giuridica',
                'is_person' => false,
                'is_company' => true,
                'privacy_role' => 'Titolare Autonomo',
                'purpose' => 'Erogazione di servizi di mediazione creditizia a società',
                'data_subjects' => 'Legali rappresentanti, soci, titolari effettivi',
                'data_categories' => 'Dati anagrafici, societari, finanziari',
                'retention_period' => '10 anni dalla cessazione del rapporto',
                'extra_eu_transfer' => 'Nessuno',
                'security_measures' => 'Accesso riservato, cifratura a riposo',
            ],
            [
                'name' => 'Titolare Effettivo',
                'is_person' => true,
                'is_company' => false,
                'privacy_role' => 'Interessato',
                'purpose' => 'Adeguata verifica antiriciclaggio (AML)',
                'data_subjects' => 'Titolari effettivi di clienti persone giuridiche',
                'data_categories' => 'Dati anagrafici, quote di partecipazione',
                'retention_period' => '10 anni dalla cessazione del rapporto',
                'extra_eu_transfer' => 'Nessuno',
                'security_measures' => 'Accesso riservato, cifratura a riposo',
            ],
            [
                'name' => 'Garante',
                'is_person' => true,
                'is_company' => false,
                'privacy_role' => 'Interessato',
                'purpose' => 'Gestione della garanzia prestata sul finanziamento',
                'data_subjects' => 'Garanti/cointestatari di pratiche di finanziamento',
                'data_categories' => 'Dati anagrafici, reddituali, finanziari',
                'retention_period' => '10 anni dalla cessazione del rapporto',
                'extra_eu_transfer' => 'Nessuno',
                'security_measures' => 'Accesso riservato, cifratura a riposo',
            ],
            [
                'name' => 'Lead / Contatto Commerciale',
                'is_person' => true,
                'is_company' => false,
                'privacy_role' => 'Interessato',
                'purpose' => 'Attività di marketing e acquisizione clientela',
                'data_subjects' => 'Potenziali clienti contattati dalla rete commerciale',
                'data_categories' => 'Dati anagrafici e di contatto',
                'retention_period' => '24 mesi dall\'ultimo contatto, salvo conversione a cliente',
                'extra_eu_transfer' => 'Nessuno',
                'security_measures' => 'Accesso riservato, cifratura a riposo',
            ],
        ];

        foreach ($tipi as $tipo) {
            ClientType::updateOrCreate(
                ['name' => $tipo['name']],
                $tipo
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\PraticaRequisito;
use Illuminate\Database\Seeder;

class PraticaRequisitiSeeder extends Seeder
{
    public function run(): void
    {
        $requisiti = [
            [
                'codice' => 'certificato_stipendio',
                'name' => 'Certificato di Stipendio / Attestato di Servizio',
                'descrizione' => 'Rilasciato dal datore di lavoro con indicazione di TFR, anzianità e trattenute.',
            ],
            [
                'codice' => 'polizza_vita',
                'name' => 'Polizza Rischio Vita',
                'descrizione' => 'Copertura assicurativa per il rischio premorienza del debitore.',
            ],
            [
                'codice' => 'polizza_impiego',
                'name' => 'Polizza Rischio Impiego / Perdit Lavoro',
                'descrizione' => 'Copertura assicurativa per la perdita involontaria dell\'impiego.',
            ],
            [
                'codice' => 'atto_benestare',
                'name' => 'Atto di Benestare',
                'descrizione' => 'Benestare firmato dal datore di lavoro per la presa in carico della trattenuta.',
            ],
            [
                'codice' => 'quota_cedibile',
                'name' => 'Comunicazione Quota Cedibile INPS',
                'descrizione' => 'Documento emesso dall\'INPS per determinare la quota massima cedibile sulla pensione.',
            ],
            [
                'codice' => 'perizia_immobile',
                'name' => 'Perizia Tecnico-Immobiliare',
                'descrizione' => 'Relazione di stima dell\'immobile redatta dal perito incaricato dalla banca.',
            ],
            [
                'codice' => 'relazione_notarile',
                'name' => 'Relazione Notarile Preliminare (RNT)',
                'descrizione' => 'Certificazione del notaio sulla continuità delle trascrizioni e assenza di ipoteche pregiudizievoli.',
            ],
            [
                'codice' => 'polizza_incendio',
                'name' => 'Polizza Incendio e Scoppio Immobile',
                'descrizione' => 'Copertura assicurativa obbligatoria sull\'immobile oggetto di ipoteca.',
            ],
            [
                'codice' => 'quantificazione_tfs',
                'name' => 'Prospetto Quantificazione TFS / TFR INPS',
                'descrizione' => 'Certificato INPS con il calcolo dell\'ammontare netto del TFS spettante al pensionato.',
            ],
            [
                'codice' => 'garanzia_mcc',
                'name' => 'Delibera Garanzia Fondo Centrale MCC',
                'descrizione' => 'Esito di ammissione alla garanzia statale del MedioCredito Centrale.',
            ],
        ];

        foreach ($requisiti as $req) {
            PraticaRequisito::updateOrCreate(
                ['codice' => $req['codice']],
                $req
            );
        }
    }
}

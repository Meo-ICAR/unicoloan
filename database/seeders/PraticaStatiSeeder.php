<?php

namespace Database\Seeders;

use App\Models\PraticaStato;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PraticaStatiSeeder extends Seeder
{
    public function run(): void
    {
        $stati = [
            // --- FASE 1: INSERIMENTO E PREVALUTAZIONE ---
            ['name' => 'Bozza',                         'ordine' => 5,   'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'gray'],
            ['name' => 'Inserita',                       'ordine' => 10,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'gray'],
            ['name' => 'Inserito',                       'ordine' => 10,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'gray'],
            ['name' => 'ACCETTATO PREVENTIVO',            'ordine' => 20,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'Richiesta Istruttoria',           'ordine' => 30,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'INVIO IN ISTRUTTORIA',            'ordine' => 40,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],

            // --- FASE 2: ISTRUTTORIA E SOSPENSIONI ---
            ['name' => 'Sospesa Istruttoria Interna',     'ordine' => 50,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],
            ['name' => 'SOSPESA',                         'ordine' => 50,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],
            ['name' => 'Caricata Banca',                  'ordine' => 60,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'In attesa documenti originali',   'ordine' => 70,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],
            ['name' => 'FASCICOLO COMPLETO',              'ordine' => 75,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],

            // ❌ RIFIUTO BANCA IN ISTRUTTORIA (Ordine 79 < Delibera 95)
            ['name' => 'DECLINATA',                       'ordine' => 79,  'is_rejected' => 1, 'is_working' => 0, 'is_estingued' => 0, 'colore' => 'danger'],

            // --- FASE 3: POLIZZE E DELIBERA ---
            ['name' => 'Richiesta Polizza',               'ordine' => 80,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],
            ['name' => 'RIENTRO POLIZZA',                 'ordine' => 85,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'Approvata',                       'ordine' => 90,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'success'],
            ['name' => 'DELIBERATA',                      'ordine' => 95,  'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'success'],

            // --- FASE 4: EMISSIONE, NOTIFICA E BENESTARE ---
            ['name' => 'RICHIESTA EMISSIONE',             'ordine' => 100, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],
            ['name' => 'NOTIFICA',                        'ordine' => 105, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'RIENTRO BENESTARE',               'ordine' => 110, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'info'],
            ['name' => 'ATTO FISSATO',                    'ordine' => 115, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'warning'],

            // ❌ RIFIUTI/ANNULLAMENTI POST-DELIBERA
            ['name' => 'RINUNCIA CLIENTE',                'ordine' => 119, 'is_rejected' => 1, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'danger'],
            ['name' => 'PRATICA RESPINTA',                'ordine' => 119, 'is_rejected' => 1, 'is_working' => 0, 'is_estingued' => 0, 'colore' => 'danger'],

            // --- FASE 5: LIQUIDAZIONE E CHIUSURA ---
            ['name' => 'PERFEZIONATA',                    'ordine' => 120, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'success'],
            ['name' => 'LIQUIDATA',                       'ordine' => 130, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'success'],
            ['name' => 'Fatturato',                       'ordine' => 140, 'is_rejected' => 0, 'is_working' => 1, 'is_estingued' => 0, 'colore' => 'success'],
            ['name' => 'Chiusa',                          'ordine' => 150, 'is_rejected' => 0, 'is_working' => 0, 'is_estingued' => 0, 'colore' => 'gray'],

            // --- FASE 6: FINE CICLO DI VITA ---
            ['name' => 'IN AMMORTAMENTO',                 'ordine' => 200, 'is_rejected' => 0, 'is_working' => 0, 'is_estingued' => 1, 'colore' => 'success'],
            ['name' => 'RINNOVABILE',                     'ordine' => 200, 'is_rejected' => 0, 'is_working' => 0, 'is_estingued' => 1, 'colore' => 'warning'],
            ['name' => 'ESTINTO',                         'ordine' => 210, 'is_rejected' => 0, 'is_working' => 0, 'is_estingued' => 1, 'colore' => 'gray'],
        ];

        foreach ($stati as $stato) {
            $codice = Str::slug($stato['name'], '_');

            PraticaStato::updateOrCreate(
                ['codice' => $codice ?: 'bozza'],
                [
                    'name' => $stato['name'],
                    'ordine' => $stato['ordine'],
                    'is_rejected' => $stato['is_rejected'],
                    'is_working' => $stato['is_working'],
                    'is_estingued' => $stato['is_estingued'],
                    'colore' => $stato['colore'],
                ]
            );
        }
    }
}

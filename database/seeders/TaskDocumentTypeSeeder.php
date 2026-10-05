<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * 1. Definiamo la mappa esatta delle relazioni.
         * Come chiave usiamo lo slug del Task (o il name).
         * Come valore usiamo l'array degli slug dei DocumentType specifici per quel task.
         */
        $taskDocumentMap = [
            'onboarding' => [
                'casellario-giudiziale',
                'carichi-pendenti',
                'carta-identita',
                'patente',
                'titolo-di-studio',
                'incarico-mediazione',
                'modulo-aml',
                'privacy-informativa',
                'prova-valutativa-oam',
                'attestato-professionale',
                'formazione-15h-aggiornamento-oam',
                'polizza-rc',
            ],
            'oam-agenti' => [
                'casellario-giudiziale',
                'carichi-pendenti',
                'dichiarazione-sostitutiva-certificato-onorabilita',
                'formazione-30h-aggiornamento-oam',
                'polizza-rc',  // Spesso richiesta per il rinnovo
            ],
            'oam-cda' => [
                'casellario-giudiziale',
                'carichi-pendenti',
                'dichiarazione-sostitutiva-certificato-onorabilita',
                'formazione-30h-aggiornamento-oam',
                'polizza-rc',  // Spesso richiesta per il rinnovo
            ],
            'oam-dipendenti' => [
                'casellario-giudiziale',
                'carichi-pendenti',
                'dichiarazione-sostitutiva-certificato-onorabilita',
                'formazione-30h-aggiornamento-oam',
                'polizza-rc',  // Spesso richiesta per il rinnovo
            ],
            'IVASS-agenti' => [
                'casellario-giudiziale',
                'carichi-pendenti',
                'dichiarazione-sostitutiva-certificato-onorabilita',
                'formazione-15h-aggiornamento-oam',
                'formazione-30h-aggiornamento-IVASS',
                'polizza-rc',  // Spesso richiesta per il rinnovo
            ],
            'oam-semestrale' => [
                'codice-etico',
                'transparency-doc',
                'proc-reclami-ricezione',
                'incarico-mediazione',
                'requisiti-organizzativi',
                'trasparenza-web',
                'trasparenza-avviso',
                'foglio-informativo',
                'modulo-aml',
                'privacy-informativa',
                'proc-compliance',
                'incarico-mediazione',
                'proc-internal-audit',
                'proc-aml-verifica',
                'requisiti-organizzativi',
            ],
            'renewal' => [
                'polizza-rc',
                'formazione-15h-aggiornamento-oam',  // O altre formazioni periodiche
                'formazione-30h-aggiornamento-oam',
                'casellario-giudiziale',  // A volte richiesti aggiornati
                'carichi-pendenti',
            ],
            'audit' => [
                'modulo-esito-audit',
                'proc-internal-audit',
                'proc-aml-verifica',
                'proc-compliance-risk',
                'proc-reclami-ricezione',
                'proc-reclami-info',
            ],
            'ispezione' => [
                'nomina-incaricato',
                'nomina-responsabile',
                'nomina-amministratore',
                'codice-etico',
                'privacy-web',
                'privacy-informativa',
            ],
            'offboarding' => [
                // In genere meno documenti in fase di uscita, ma potresti richiedere revoche o moduli specifici
                'modulo-esito-audit',  // Forse come verifica finale
                // Aggiungi eventuali documenti di revoca mandato se li crei in futuro
            ],
        ];

        // 2. Cicliamo sulla mappa delle relazioni
        foreach ($taskDocumentMap as $taskSlug => $docSlugs) {
            // Cerchiamo il task nel DB tramite lo slug (che non cambia mai)
            $task = Task::where('name', $taskSlug)->first();

            if (!$task) {
                // Se per caso il task non esiste (es. refactoring o errore di battitura), salta per evitare crash
                continue;
            }

            /*
             * 3. Recuperiamo TUTTI i DocumentType associati a questo task in un'unica query.
             * Usiamo il whereIn che accetta l'array di slug.
             */
            $documentTypes = DocumentType::whereIn('slug', $docSlugs)->get();

            $pivotData = [];

            // 4. Prepariamo l'array per la tabella ponte con i dati dinamici appena pescati
            foreach ($documentTypes as $type) {
                $pivotData[$type->id] = [
                    'slug' => $type->slug,  // Eredita lo slug dal tipo di documento
                    'is_required' => true,  // Tutti obbligatori, o gestisci logiche specifiche
                ];
            }

            // 5. Inserimento massivo a database (1 sola query per Task)
            if (!empty($pivotData)) {
                $task->documentTypes()->attach($pivotData);
            }
        }
    }
}

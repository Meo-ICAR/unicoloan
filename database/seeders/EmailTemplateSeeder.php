<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'code' => 'AUDIT_DESK',
                'name' => 'Annuncio Attività di Audit (Desk/Da remoto)',
                'subject' => 'Preavviso attività di audit di conformità',
                'body' => "<p>Gentile <strong>{agente_nome}</strong>,</p>
                           <p>Con la presente ti informiamo che nel periodo tra il <strong>{data_inizio}</strong> e il <strong>{data_fine}</strong> avvieremo una periodica attività di audit desk sulla tua operatività.</p>
                           <p>Ti ricordiamo che l'audit è volto a verificare il rispetto delle normative vigenti e delle procedure interne. Potremmo contattarti per richiedere integrazioni documentali sulle pratiche selezionate.</p>
                           <p>Cordiali saluti,<br>Il team Compliance</p>",
                'placeholders' => json_encode(['{agente_nome}', '{data_inizio}', '{data_fine}']),
                'is_active' => true,
            ],
            [
                'code' => 'INSPECTION_ONSITE',
                'name' => 'Preavviso Ispezione in Sede',
                'subject' => 'Comunicazione di ispezione in sede programmata',
                'body' => "<p>Gentile <strong>{agente_nome}</strong>,</p>
                           <p>Ti preannunciamo che in data <strong>{data_ispezione}</strong> alle ore <strong>{ora_ispezione}</strong>, un nostro incaricato effettuerà un'ispezione presso la tua sede operativa situata in <strong>{indirizzo_sede}</strong>.</p>
                           <p>Ti preghiamo di garantire la presenza in sede o di delegare un tuo collaboratore autorizzato e di predisporre la documentazione antiriciclaggio e trasparenza per la visione.</p>
                           <p>Per eventuali impedimenti o richieste di riprogrammazione, ti preghiamo di rispondere a questa email entro 48 ore.</p>
                           <p>Cordiali saluti,<br>Il team Ispezioni e Controlli</p>",
                'placeholders' => json_encode(['{agente_nome}', '{data_ispezione}', '{ora_ispezione}', '{indirizzo_sede}']),
                'is_active' => true,
            ],
            [
                'code' => 'DOC_EXPIRING',
                'name' => 'Promemoria Scadenza Documentazione',
                'subject' => 'Attenzione: Scadenza documentazione imminente ({documento_nome})',
                'body' => "<p>Gentile <strong>{agente_nome}</strong>,</p>
                           <p>Ti scriviamo per ricordarti che i seguenti documenti presenti nei nostri archivi sono in scadenza o scaduti:</p>
                           {elenco_documenti}
                           <p>Al fine di mantenere attivo il tuo mandato operativo senza interruzioni, ti invitiamo a caricare i documenti aggiornati all'interno del portale o a inviarli in risposta a questa email il prima possibile.</p>
                           <p>Restiamo a disposizione per qualsiasi chiarimento.</p>
                           <p>Cordiali saluti,<br>Ufficio Rete</p>",
                'placeholders' => json_encode(['{agente_nome}', '{documento_nome}', '{data_scadenza}', '{elenco_documenti}']),
                'is_active' => true,
            ],
            [
                'code' => 'PEC_VERIFICATION',
                'name' => 'Verifica Attivazione ed Esistenza PEC',
                'subject' => 'Richiesta di riscontro per verifica indirizzo PEC',
                'body' => "<p>Gentile <strong>{agente_nome}</strong>,</p>
                           <p>Questa è una comunicazione automatica di sistema generata per verificare l'effettiva operatività della casella di Posta Elettronica Certificata (PEC) associata alla tua anagrafica: <strong>{indirizzo_pec}</strong>.</p>
                           <p>Ti chiediamo cortesemente di <strong>rispondere a questa email</strong> per confermare la ricezione del messaggio e l'attiva presidiabilità della casella PEC in oggetto.</p>
                           <p>Cordiali saluti,<br>Amministrazione</p>",
                'placeholders' => json_encode(['{agente_nome}', '{indirizzo_pec}']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['code' => $template['code']],  // Cerca per codice univoco
                $template  // Aggiorna o crea con questi dati
            );
        }
    }
}

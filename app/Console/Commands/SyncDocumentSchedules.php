<?php

namespace App\Console\Commands;

use App\Models\DocumentSchedule;
use App\Services\DocumentReminderService;
use App\Support\DocumentRecipientResolver;
use Illuminate\Console\Command;

class SyncDocumentSchedules extends Command
{
    protected $signature = 'documents:sync-schedule';
    protected $description = 'Sincronizza la tabella  dello scadenziario documenti';

    public function handle()
    {
        $reminderService = app(DocumentReminderService::class);
        $recipientResolver = app(DocumentRecipientResolver::class);

        // Prendiamo tutti i documenti computati dal servizio
        $documents = $reminderService->scheduleQuery()->get();

        $rows = [];

        foreach ($documents as $doc) {
            // Estraiamo il nome dell'entità polimorfica una volta sola qui nel backend
            $entityName = $doc->documentable?->name
                ?? $doc->documentable?->protocol_number
                ?? $doc->documentable?->summary
                ?? '-';

            $rows[] = [
                'document_id' => $doc->id,
                'documentable_group_key' => $doc->documentable_type . '|' . $doc->documentable_id,
                'document_name' => $doc->name,
                'document_type_name' => $doc->documentType?->name ?? '-',
                'entity_name' => $entityName,
                'documentable_type' => $doc->documentable_type,
                'documentable_id' => $doc->documentable_id,
                'expires_at' => $doc->expires_at?->toDateString(),
                'days_until_expiry' => $reminderService->daysUntilExpiry($doc),
                'status' => $doc->status,
                'reminders_count' => $doc->reminders_count ?? $doc->reminders()->count(),
                'last_sent_at' => $doc->last_sent_at,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Transazione per azzerare e riscrivere i dati senza downtime sulla UI
        \DB::transaction(function () use ($rows) {
            DocumentSchedule::truncate();

            // Inserimento a blocchi per performance
            foreach (array_chunk($rows, 500) as $chunk) {
                DocumentSchedule::insert($chunk);
            }
        });

        $this->info('Scadenziario sincronizzato con successo.');
    }
}

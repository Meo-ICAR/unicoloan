<?php

namespace App\Console\Commands;

use App\Services\DocumentReminderService;
use Illuminate\Console\Command;

class SendDocumentRemindersCommand extends Command
{
    protected $signature = 'documents:send-reminders {--force : Invia anche se non in giornata di preavviso}';

    protected $description = 'Invia email di sollecito per i documenti in scadenza, raggruppate per entità collegata';

    public function handle(DocumentReminderService $service): int
    {
        $onlyDueToday = ! $this->option('force');

        $this->info($onlyDueToday
            ? 'Invio solleciti per soglie di preavviso odierne...'
            : 'Invio solleciti forzato per tutti i documenti nello scadenziario...');

        $stats = $service->sendReminders($onlyDueToday);

        $this->table(
            ['Metrica', 'Valore'],
            collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        return self::SUCCESS;
    }
}

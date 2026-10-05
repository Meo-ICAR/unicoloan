<?php

namespace App\Console\Commands;

use App\Models\Resource as ResourceModel;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncResourcesCommand extends Command
{
    protected $signature = 'permissions:sync-resources';

    protected $description = 'Scansiona le risorse Filament e le sincronizza nel DB per l\'applicazione corrente';

    public function handle(): int
    {
        $appName = config('app.name');
        $this->info("Inizio sincronizzazione risorse per l'applicazione: [ {$appName} ]");

        $count = 0;

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                try {
                    // 1. Ricava la chiave/slug univoca della risorsa
                    $key = method_exists($resourceClass, 'getSlug')
                        ? $resourceClass::getSlug()
                        : Str::kebab(class_basename($resourceClass));

                    // 2. Ricava l'etichetta del modello
                    $name = method_exists($resourceClass, 'getModelLabel')
                        ? Str::title($resourceClass::getModelLabel())
                        : class_basename($resourceClass);

                    // 3. Ricava il gruppo di navigazione usando il metodo pubblico di Filament
                    $group = method_exists($resourceClass, 'getNavigationGroup')
                        ? $resourceClass::getNavigationGroup()
                        : null;

                    // 4. Inserisce o mantiene invariata la risorsa se già presente
                    ResourceModel::firstOrCreate(
                        [
                            'app_name' => $appName,
                            'key' => $key,
                        ],
                        [
                            'name' => $name,
                            'group' => $group,
                            'min_plan' => 'BASE', // Piano minimo di default per le nuove risorse
                        ]
                    );

                    $count++;
                } catch (\Throwable $e) {
                    $this->warn("Impossibile sincronizzare la risorsa {$resourceClass}: ".$e->getMessage());
                }
            }
        }

        $this->info("Sincronizzazione completata! Processate {$count} risorse per {$appName}.");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Enums;

use App\Models\Resource;

enum PlanType: string
{
    case Base = 'BASE';
    case Medium = 'MEDIUM';
    case Full = 'FULL';

    /**
     * Verifica se questo piano copre la feature (chiave/slug della risorsa Filament),
     * confrontandola con Resource::min_plan tramite Resource::isAccessibleWithPlan().
     * Se la risorsa non è ancora censita in `resources` (non sincronizzata con
     * permissions:sync-resources), il piano non blocca l'accesso: il gating si
     * applica solo alle risorse effettivamente registrate.
     */
    public function hasFeature(string $feature, ?string $callerClass = null): bool
    {
        $resource = Resource::query()
            ->forCurrentApp()
            ->where('key', $feature)
            ->first();

        if (! $resource) {
            return true;
        }

        return $resource->isAccessibleWithPlan($this->value);
    }
}

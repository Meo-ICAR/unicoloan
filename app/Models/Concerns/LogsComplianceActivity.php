<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity as SpatieLogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit trail per i modelli rilevanti ai fini regolamentari (audit, reclami,
 * SOS, rimedi). Registra creazione/modifica/cancellazione con il dettaglio dei
 * campi cambiati e chi li ha cambiati, su un log dedicato per modello.
 */
trait LogsComplianceActivity
{
    use SpatieLogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }
}

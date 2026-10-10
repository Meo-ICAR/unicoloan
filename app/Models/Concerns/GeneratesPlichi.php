<?php

namespace App\Models\Concerns;

use App\Models\Task;

/**
 * Alla creazione del record e quando cambia un campo che governa i plichi documentali (es. stato_pratica),
 * crea i documenti richiesti dai plichi che ora risultano applicabili. Non cancella mai nulla.
 */
trait GeneratesPlichi
{
    protected static function bootGeneratesPlichi(): void
    {
        static::saved(function (self $record): void {
            if ($record->wasRecentlyCreated || $record->wasChanged(Task::watchedFieldsFor($record))) {
                Task::applyPlichiTo($record);
            }
        });
    }
}

<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasRelationPlanAccess
{
    /**
     * Controlla la visibilità della scheda RelationManager nel tab del record principale
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Piano e permessi per EmployeeType sono gia' valutati da checkPiano(),
        // come per le Resource (HasPlanAccess): un utente senza profilo Employee
        // non deve perdere le schede di un record che puo' aprire.
        return \checkPiano(static::getFeatureKey(), static::class);
    }

    public static function getFeatureKey(): string
    {
        if (property_exists(static::class, 'featureKey') && static::$featureKey !== null) {
            return static::$featureKey;
        }

        if (method_exists(static::class, 'getRelationshipName')) {
            return static::getRelationshipName();
        }

        if (property_exists(static::class, 'relationship') && static::$relationship !== null) {
            return static::$relationship;
        }

        return Str::kebab(class_basename(static::class));
    }
}

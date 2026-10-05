<?php

namespace App\Filament\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasRelationPlanAccess
{
    /**
     * Controlla la visibilità della scheda RelationManager nel tab del record principale
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $featureKey = static::getFeatureKey();

        // 1. Verifica Piano
        if (! \checkPiano($featureKey, static::class)) {
            return false;
        }

        // 2. Verifica Permessi Utente/Employee
        /** @var User|null $user */
        $user = auth()->user();

        return $user ? $user->hasPermission($featureKey, 'viewAny') : false;
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

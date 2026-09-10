<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPlanAccess
{
    /**
     * Controlla la visibilità del menu di navigazione (per Resource e Page)
     */
    public static function shouldRegisterNavigation(): bool
    {
        // Se la risorsa appartiene al gruppo Settings e checkPiano('settings') è false, nascondila
        if (static::$navigationGroup === 'Settings' && ! \checkPiano('settings')) {
            return false;
        }

        return \checkPiano(static::getFeatureKey(), static::class);
    }

    /**
     * Controlla la visibilità delle schede/relazioni (per RelationManager)
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // 🔹 Il "\" dice a PHP di cercare la funzione a livello GLOBALE
        return \checkPiano(static::getFeatureKey(), static::class);
    }

    /**
     * Gate unico piano + ruolo, riusato da tutti i metodi di autorizzazione.
     *
     * Prima nascondevamo la risorsa solo dalla navigazione: le pagine restavano
     * raggiungibili via URL diretto (es. /admin/audits/1/edit). Con questi
     * override il controllo checkPiano() viene applicato anche all'accesso CRUD,
     * cosi' un ruolo che non ha la feature non puo' aprire la risorsa nemmeno
     * conoscendone l'URL. Quando verranno introdotte vere Policy di modello,
     * questi override potranno essere rimossi in favore della Policy.
     */
    protected static function hasPlanFeatureAccess(): bool
    {
        return \checkPiano(static::getFeatureKey(), static::class);
    }

    public static function canViewAny(): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canCreate(): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::hasPlanFeatureAccess();
    }

    public static function canRestore(Model $record): bool
    {
        return static::hasPlanFeatureAccess();
    }

    /**
     * Helper interno per ricavare la chiave della feature in modo intelligente
     */
    protected static function getFeatureKey(): string
    {
        // 1. Priorità massima: $featureKey personalizzato nella classe
        if (property_exists(static::class, 'featureKey') && static::$featureKey !== null) {
            return static::$featureKey;
        }

        // 2. Se è un RelationManager, tenta di leggere la relazione
        if (method_exists(static::class, 'getRelationshipName')) {
            return static::getRelationshipName();
        }
        if (property_exists(static::class, 'relationship') && static::$relationship !== null) {
            return static::$relationship;
        }

        // 3. Se è una Resource/Page, tenta di leggere lo slug di Filament
        if (method_exists(static::class, 'getSlug')) {
            return static::getSlug();
        }

        // 4. Fallback generale: nome della classe convertito in kebab-case
        return Str::kebab(class_basename(static::class));
    }
}

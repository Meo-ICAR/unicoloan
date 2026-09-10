<?php

use App\Enums\PlanType;
use App\Enums\UserRole;

if (! function_exists('checkPiano')) {
    /**
     * Verifica se una funzionalità è accessibile considerando sia il PIANO che
     * il RUOLO UTENTE.
     *
     * Il risultato è memoizzato per la durata della richiesta: checkPiano()
     * viene invocato molte volte per ogni render della navigazione Filament
     * (un controllo per ogni azione CRUD di ogni risorsa) e piano/ruolo non
     * cambiano nel corso della stessa richiesta.
     */
    function checkPiano(string $feature, ?string $callerClass = null): bool
    {
        static $cache = [];

        $userId = auth()->id() ?? 'guest';
        // Il valore del piano entra nella chiave cosi' i test che lo cambiano
        // a runtime non leggono un risultato memoizzato sotto un piano diverso.
        $key = $feature.'|'.$userId.'|'.config('plan.type', PlanType::FULL->value);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $cache[$key] = resolvePianoAccess($feature, $callerClass);
    }
}

if (! function_exists('resolvePianoAccess')) {
    function resolvePianoAccess(string $feature, ?string $callerClass): bool
    {
        // STEP 1: Piano / licenza.
        $planValue = config('plan.type', PlanType::FULL->value);
        $plan = PlanType::tryFrom((string) $planValue) ?? PlanType::FULL;

        if (! $plan->hasFeature($feature, $callerClass)) {
            return false;
        }

        // STEP 2: Ruolo utente (RBAC).
        $user = auth()->user();

        if ($user && ! empty($user->role)) {
            $role = $user->role instanceof UserRole
                ? $user->role
                : UserRole::tryFrom($user->role);

            if ($role && ! $role->hasFeature($feature)) {
                return false;
            }
        }

        return true;
    }
}

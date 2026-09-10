<?php

use App\Enums\PlanType;
use App\Enums\UserRole;

if (! function_exists('checkPiano')) {
    /**
     * Verifica se una funzionalità è accessibile considerando sia il PIANO che il RUOLO UTENTE.
     */
    function checkPiano(string $feature, ?string $callerClass = null): bool
    {
        // -------------------------------------------------------------
        // STEP 1: Controllo del PIANO (Licenza/Abbonamento)
        // -------------------------------------------------------------
        $planValue = config('plan.type', PlanType::FULL->value);
        $plan = PlanType::tryFrom((string) $planValue) ?? PlanType::FULL;

        $hasPlanAccess = $plan->hasFeature($feature, $callerClass);

        // Se il Piano blocca la feature, stop immediato (nessuno può vederla)
        if (! $hasPlanAccess) {
            return false;
        }

        // -------------------------------------------------------------
        // STEP 2: Controllo del RUOLO UTENTE (RBAC)
        // -------------------------------------------------------------
        $user = auth()->user();

        if ($user && ! empty($user->role)) {
            // Compatibile sia se $user->role è una stringa che se è già un Enum
            $role = $user->role instanceof UserRole
                ? $user->role
                : UserRole::tryFrom($user->role);

            // Se il ruolo esiste ed esclude questa feature, nega l'accesso
            if ($role && ! $role->hasFeature($feature)) {
                return false;
            }
        }

        return true;
    }
}

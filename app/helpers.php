<?php

use App\Enums\PlanType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmployeeTypePermission;
use App\Models\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

if (! function_exists('checkPiano')) {
    /**
     * Verifica se una funzionalità è accessibile considerando sia il PIANO che
     * il RUOLO UTENTE (EmployeeType).
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
        $key = $feature.'|'.$userId.'|'.config('plan.type', PlanType::Full->value);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $cache[$key] = resolvePianoAccess($feature, $callerClass);
    }
}

if (! function_exists('resolvePianoAccess')) {
    function resolvePianoAccess(string $feature, ?string $callerClass): bool
    {
        // STEP 0: Bypass totale per Admin/SuperAdmin (App\Enums\UserRole),
        // che vedono sempre tutto indipendentemente da piano e ruolo EmployeeType.
        $authUser = auth()->user();

        if ($authUser && ! empty($authUser->role)) {
            $userRole = $authUser->role instanceof UserRole
                ? $authUser->role
                : UserRole::tryFrom($authUser->role);

            if ($userRole === UserRole::ADMIN || $userRole === UserRole::SUPER_ADMIN) {
                return true;
            }
        }

        // STEP 1: Piano / licenza.
        $planValue = config('plan.type', PlanType::Full->value);
        $plan = PlanType::tryFrom((string) $planValue) ?? PlanType::Full;

        if (! $plan->hasFeature($feature, $callerClass)) {
            return false;
        }

        // STEP 2: Ruolo utente, tramite EmployeeType (vedi
        // app/Filament/Resources/EmployeeTypes e EmployeeTypePermission).
        // Se non si riesce a risolvere alcun ruolo per l'utente, il controllo
        // viene saltato e l'accesso resta deciso solo dal piano (STEP 1) —
        // fail open per gli utenti senza ruolo assegnato.
        $employeeTypeIds = resolveUserEmployeeTypeIds(auth()->user());

        if (! empty($employeeTypeIds)) {
            $resourceId = Resource::query()
                ->forCurrentApp()
                ->where('key', $feature)
                ->value('id');

            if ($resourceId && ! EmployeeTypePermission::query()
                ->whereIn('employee_type_id', $employeeTypeIds)
                ->where('resource_id', $resourceId)
                ->exists()) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('resolveUserEmployeeTypeIds')) {
    /**
     * Risolve gli EmployeeType (ruoli) dell'utente loggato, a partire dal suo
     * profilo Employee ed Employee::employee_roles (JSON, un dipendente può
     * avere più ruoli). Ritorna un array vuoto per utenti senza profilo
     * Employee o senza ruoli assegnati.
     *
     * Il profilo viene risolto prima tramite User::profile() (morphTo, come
     * in unicobpm), poi tramite User::employee() se presente: quest'ultimo è
     * il collegamento già popolato in questa applicazione.
     */
    function resolveUserEmployeeTypeIds(mixed $user): array
    {
        if (! $user) {
            return [];
        }

        $employee = method_exists($user, 'profile') ? $user->profile : null;

        if (! $employee instanceof Employee && method_exists($user, 'employee')) {
            $related = $user->employee;
            $employee = $related instanceof Collection ? $related->first() : $related;
        }

        if (! $employee instanceof Employee) {
            return [];
        }

        $roles = $employee->employee_roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        $roles = Arr::wrap($roles);

        if (empty($roles)) {
            return [];
        }

        return EmployeeType::query()->whereIn('name', $roles)->pluck('id')->all();
    }
}

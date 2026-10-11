<?php

use Unico\Core\Access\AccessChecker;

if (! function_exists('checkPiano')) {
    /**
     * Verifica se una funzionalità è accessibile all'utente: piano della sua company per questa app e ruoli EmployeeType
     * (vedi Unico\Core\Access\AccessChecker). Il risultato è memorizzato per la durata della richiesta.
     */
    function checkPiano(string $feature, ?string $callerClass = null): bool
    {
        return app(AccessChecker::class)->check($feature, $callerClass);
    }
}

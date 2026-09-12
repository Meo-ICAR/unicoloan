<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;

/**
 * Centralizza il controllo "questo agente/dipendente può lavorare con questa banca?".
 *
 * Un fornitore (agente) o un employee (dipendente) possono essere messi in blacklist
 * per uno specifico istituto (Clienti): finché il blocco è attivo, non dovrebbero
 * poter essere assegnati a pratiche di quella banca.
 */
class BlacklistChecker
{
    public function isFornitoreBlacklistedForCliente(Fornitore $fornitore, Clienti $clienti): bool
    {
        return $fornitore->isBlacklistedBy($clienti->id);
    }

    public function isEmployeeBlacklistedForCliente(Employee $employee, Clienti $clienti): bool
    {
        return $employee->isBlacklistedBy($clienti->id);
    }

    /**
     * Risolve un Fornitore per nome/piva e verifica se è bloccato per la banca indicata.
     * Ritorna false se agente o banca non sono riconosciuti (nessun blocco applicabile).
     */
    public function isAgenteNameBlacklistedForBancaName(?string $agenteName, ?string $bancaName): bool
    {
        if (blank($agenteName) || blank($bancaName)) {
            return false;
        }

        $fornitore = Fornitore::where('name', $agenteName)->orWhere('piva', $agenteName)->first();
        $clienti = Clienti::where('name', $bancaName)->first();

        if (! $fornitore || ! $clienti) {
            return false;
        }

        return $this->isFornitoreBlacklistedForCliente($fornitore, $clienti);
    }
}

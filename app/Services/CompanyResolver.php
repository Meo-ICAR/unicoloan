<?php

namespace App\Services;

use App\Models\Company;
use RuntimeException;

class CompanyResolver
{
    /**
     * Risolve la Company attiva del sistema (single-tenant).
     *
     * Ritorna sempre il primo record nella tabella companies.
     * Lancia eccezione se non esiste alcuna company configurata.
     *
     * @throws RuntimeException
     */
    public function resolve(): Company
    {
        $company = Company::first();

        if (! $company) {
            throw new RuntimeException(
                'Nessuna Company configurata nel sistema. '.
                'Creare almeno un record nella tabella companies prima di procedere.'
            );
        }

        return $company;
    }

    /**
     * Risolve e ritorna solo l'UUID della Company attiva.
     *
     * @throws RuntimeException
     */
    public function resolveId(): string
    {
        return $this->resolve()->id;
    }
}

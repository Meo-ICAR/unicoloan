<?php

namespace App\Services\Kyc;

/**
 * Nomi dei campi anagrafici esposti per ogni persona del QAV (esecutore, titolari effettivi).
 *
 * I valori `kyc.*` sono costruiti da ModuleDataResolver::kycValues(), che riusa i suoi helper
 * privati (sede principale, indirizzo, documento d'identita'); qui resta solo l'elenco dei campi.
 */
final class KycModuleValues
{
    /**
     * @var array<int, string>
     */
    public const PERSON_FIELDS = [
        'name', 'first_name', 'tax_code', 'birth_place', 'birth_date', 'citizenship', 'sex',
        'city', 'province', 'address', 'zip',
        'doc_type', 'doc_number', 'doc_issuer', 'doc_issued_at', 'doc_expires_at',
    ];
}

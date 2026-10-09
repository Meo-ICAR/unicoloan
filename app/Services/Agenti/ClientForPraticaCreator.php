<?php

namespace App\Services\Agenti;

use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Cliente in anagrafica per una pratica inserita dal produttore: si riusa quello con lo stesso codice
 * fiscale / partita IVA (senza toccarne i dati), altrimenti se ne crea uno con i soli dati minimi.
 */
class ClientForPraticaCreator
{
    public function findOrCreate(string $taxCode, bool $isCompany, string $name, ?string $firstName, string $email, string $phone): Client
    {
        $code = Str::upper(trim($taxCode));

        $existing = Client::query()->where('tax_code', $code)->orWhere('vat_number', $code)->first();

        if ($existing !== null) {
            return $existing;
        }

        return Client::query()->create([
            'is_person' => ! $isCompany,
            'is_company' => $isCompany,
            'is_client' => true,
            'name' => trim($name),
            'first_name' => $isCompany ? null : trim((string) $firstName),
            'tax_code' => $code,
            'vat_number' => $isCompany ? $code : null,
            'email' => Str::lower(trim($email)),
            'phone' => trim($phone),
        ]);
    }
}

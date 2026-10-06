<?php

namespace App\Services;

use App\Enums\ModuleSourceKey as Key;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Document;
use App\Models\PROFORMA\Pratica;

/**
 * Costruisce i dati con cui compilare i moduli PDF a partire da una Pratica e dal suo Client.
 */
class ModuleDataResolver
{
    /**
     * Codici di DocumentType che identificano un documento d'identita'.
     *
     * @var array<int, string>
     */
    public const IDENTITY_TYPE_CODES = ['CARTA_IDENTITA', 'DOCUMENTO_IDENTIFICATIVO'];

    /**
     * Nomi di DocumentType senza codice ma validi come documento d'identita'.
     *
     * @var array<int, string>
     */
    public const IDENTITY_TYPE_NAMES = ['Carta di Identità o Patente & cert. Residenza', 'Patente di Guida'];

    /**
     * Il legame pratica -> cliente e' il codice fiscale (o la P.IVA per le societa').
     */
    public function findClient(Pratica $pratica): ?Client
    {
        $code = $pratica->codice_fiscale;

        if (blank($code)) {
            return null;
        }

        return Client::query()
            ->where(fn ($query) => $query->where('tax_code', $code)->orWhere('vat_number', $code))
            ->first();
    }

    public function resolve(Pratica $pratica, Client $client): ResolvedModuleData
    {
        $pratica->loadMissing('agente');
        $client->loadMissing(['branches', 'documents.documentType', 'employer.branches', 'legalRepresentative', 'thirdPartyFinancings']);

        $branch = $this->mainBranch($client);
        $employer = $client->employer;
        $employerBranch = $employer ? $this->mainBranch($employer) : null;
        $identity = $this->identityDocument($client);
        $agent = $pratica->agente;
        $representative = $client->legalRepresentative;
        $thirdParty = $client->thirdPartyFinancings->first();

        return new ResolvedModuleData([
            Key::PraticaCodice->value => $pratica->codice_pratica,
            Key::PraticaImporto->value => $pratica->amount,
            Key::PraticaRata->value => $pratica->rata,
            Key::PraticaNumeroRate->value => $pratica->nrate,
            Key::PraticaBanca->value => $pratica->denominazione_banca,
            Key::PraticaAbi->value => $pratica->abi,
            Key::PraticaProdotto->value => $pratica->denominazione_prodotto,
            Key::PraticaDataInserimento->value => $pratica->data_inserimento_pratica,
            Key::PraticaOggi->value => now(),

            Key::ClienteCognome->value => $client->name,
            Key::ClienteNome->value => $client->first_name,
            Key::ClienteNominativo->value => $this->nominativo($client),
            Key::ClienteCodiceFiscale->value => $client->tax_code,
            Key::ClientePartitaIva->value => $client->vat_number,
            Key::ClienteEmail->value => $client->email,
            Key::ClienteTelefono->value => $client->phone,
            Key::ClienteStipendio->value => $client->salary,
            Key::ClienteIban->value => $client->iban,
            Key::ClientePersonaFisica->value => (bool) $client->is_person,
            Key::ClienteDataNascita->value => $client->birth_date,
            Key::ClienteLuogoNascita->value => $client->birth_place,
            Key::ClienteSesso->value => $client->sex,
            Key::ClienteCittadinanza->value => $client->citizenship,

            Key::ClientePec->value => $client->pec,
            Key::ClienteAteco->value => $client->ateco_code,
            Key::ClienteCciaa->value => $client->cciaa_registration,

            Key::RappresentanteNominativo->value => $representative ? $this->nominativo($representative) : null,
            Key::RappresentanteEmail->value => $representative?->email,
            Key::RappresentanteTelefono->value => $representative?->phone,

            Key::AgenteNominativo->value => $agent?->name,
            Key::AgenteIndirizzo->value => $agent ? $this->formatAddress($agent->indirizzo, $agent->cap, $agent->comune, $agent->prov) : null,
            Key::AgenteEmail->value => $agent?->email,
            Key::AgenteTelefono->value => $agent?->tel,
            Key::AgenteCodiceFiscale->value => $agent?->cf,

            Key::TerziBanca->value => $thirdParty?->denominazione_banca,
            Key::TerziProdotto->value => $thirdParty?->denominazione_prodotto,
            Key::TerziRata->value => $thirdParty?->rata,

            Key::DatoreNome->value => $employer?->name,
            Key::DatorePartitaIva->value => $employer?->vat_number,
            Key::DatoreIndirizzo->value => $employerBranch ? $this->fullAddress($employerBranch) : null,

            Key::SedeIndirizzo->value => $branch?->address,
            Key::SedeCivico->value => $branch?->street_number,
            Key::SedeCitta->value => $branch?->city,
            Key::SedeCap->value => $branch?->zip_code,
            Key::SedeProvincia->value => $branch?->province,
            Key::SedeIndirizzoCompleto->value => $branch ? $this->fullAddress($branch) : null,

            Key::DocumentoNumero->value => $identity?->docnumber,
            Key::DocumentoRilasciatoDa->value => $identity?->emitted_by,
            Key::DocumentoRilasciatoIl->value => $identity?->emitted_at,
            Key::DocumentoScadenza->value => $identity?->expires_at,
        ]);
    }

    private function nominativo(Client $client): string
    {
        return trim(($client->name ?? '').' '.($client->first_name ?? ''));
    }

    /**
     * Sede principale: la piu' recente tra quelle marcate `is_main_office`; se nessuna lo e',
     * la piu' recente tra le sedi non dismesse. Le sedi dismesse non vengono mai usate.
     */
    private function mainBranch(Client $client): ?Branch
    {
        $active = $client->branches->filter(fn (Branch $branch) => $branch->dismissed_at === null);

        $candidates = $active->where('is_main_office', true);

        if ($candidates->isEmpty()) {
            $candidates = $active;
        }

        return $candidates
            ->sortByDesc(fn (Branch $branch) => [$branch->created_at?->getTimestamp() ?? 0, (int) $branch->id])
            ->first();
    }

    private function fullAddress(Branch $branch): ?string
    {
        return $this->formatAddress(
            trim(($branch->address ?? '').' '.($branch->street_number ?? '')),
            $branch->zip_code,
            $branch->city,
            $branch->province,
        );
    }

    private function formatAddress(?string $street, ?string $zipCode, ?string $city, ?string $province): ?string
    {
        $place = trim(trim(($zipCode ?? '').' '.($city ?? '')).' '.(filled($province) ? '('.$province.')' : ''));

        $address = implode(', ', array_filter([trim((string) $street), $place]));

        return $address === '' ? null : $address;
    }

    /**
     * Ultimo documento d'identita' non scaduto del cliente (per data di rilascio).
     */
    private function identityDocument(Client $client): ?Document
    {
        return $client->documents
            ->filter(function (Document $document): bool {
                $type = $document->documentType;

                if ($type === null) {
                    return false;
                }

                $isIdentity = in_array($type->code, self::IDENTITY_TYPE_CODES, true)
                    || in_array($type->name, self::IDENTITY_TYPE_NAMES, true);

                $notExpired = $document->expires_at === null || $document->expires_at->greaterThanOrEqualTo(today());

                // Il certificato di residenza condivide il tipo combinato con la carta d'identita'.
                $isResidenceCertificate = str_contains(mb_strtolower((string) $document->name), 'residenza');

                return $isIdentity && $notExpired && ! $isResidenceCertificate;
            })
            ->sortByDesc(fn (Document $document) => [$document->emitted_at?->getTimestamp() ?? 0, $document->created_at?->getTimestamp() ?? 0])
            ->first();
    }
}

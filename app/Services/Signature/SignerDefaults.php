<?php

namespace App\Services\Signature;

use App\Models\Client;
use App\Models\Document;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;

/**
 * Firmatari proposti per un documento; gli slot non risolvibili mancano e li compila l'operatore.
 */
class SignerDefaults
{
    /**
     * @return array<string, SignerInput> indicizzato per slot
     */
    public static function for(Document $document): array
    {
        $documentable = $document->documentable;
        $signers = [];

        if ($documentable instanceof Client) {
            $signers['cliente'] = SignerInput::fromClient($documentable, 'cliente');
        }

        if ($documentable instanceof Pratica) {
            $client = app(ModuleDataResolver::class)->findClient($documentable);

            if ($client !== null) {
                $signers['cliente'] = SignerInput::fromClient($client, 'cliente');
            }

            if ($documentable->agente !== null) {
                $signers['collaboratore'] = SignerInput::fromAgent($documentable->agente, 'collaboratore');
            }
        }

        return $signers;
    }
}

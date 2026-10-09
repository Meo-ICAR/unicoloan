<?php

namespace App\Services\Kyc;

use App\Enums\KycStatus;
use App\Models\Document;
use App\Models\KycQuestionnaire;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approva un QAV: genera il PDF e solo dopo marca il questionario come approvato.
 */
class KycApprover
{
    public function __construct(private KycQavGenerator $generator) {}

    /**
     * @throws \DomainException con i requisiti mancanti o il modulo non configurato
     */
    public function approve(KycQuestionnaire $questionnaire, User $user): Document
    {
        // Istanza fresca: la relazione beneficialOwners e' in cache sul modello.
        $questionnaire = $questionnaire->fresh(['client', 'beneficialOwners']);

        $missing = $questionnaire->missingRequirements();

        if ($missing !== []) {
            throw new \DomainException('Requisiti mancanti: '.implode(', ', $missing).'.');
        }

        return DB::connection('mysql')->transaction(function () use ($questionnaire, $user): Document {
            $document = $this->generator->generate($questionnaire, $user);

            $questionnaire->update([
                'status' => KycStatus::Approved,
                'verified_by' => $user->name,
                'verified_at' => now(),
                'document_id' => $document->getKey(),
            ]);

            return $document;
        });
    }
}

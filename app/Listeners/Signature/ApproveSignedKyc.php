<?php

namespace App\Listeners\Signature;

use App\Models\KycQuestionnaire;
use App\Models\User;
use App\Enums\KycStatus;
use Unico\Core\Events\SignatureCompleted;

/**
 * Il QAV compilato dal produttore (stato Completo) diventa approvato quando il cliente lo firma; i questionari già approvati
 * dall'istruttoria restano come sono. Il firmato sta nello stesso documento, quindi il collegamento non cambia.
 */
class ApproveSignedKyc
{
    public function handle(SignatureCompleted $event): void
    {
        $signedAt = $event->request->signed_at ?? now();

        KycQuestionnaire::query()
            ->where('document_id', $event->document->getKey())
            ->where('status', KycStatus::Complete)
            ->each(fn (KycQuestionnaire $questionnaire) => $questionnaire->update([
                'status' => KycStatus::Approved,
                'verified_at' => $signedAt,
                'verified_by' => (string) (User::query()->whereKey($event->request->requested_by)->value('name') ?? 'Firma del cliente'),
            ]));
    }
}

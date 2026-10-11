<?php

namespace App\Listeners\Signature;

use Unico\Core\Events\SignatureCancelled;
use Unico\Core\Events\SignatureCompleted;
use Unico\Core\Events\SignatureSent;

/** Traccia nel registro attività gli eventi di firma del pacchetto (nessun dato personale: solo id e provider). */
class LogSignatureActivity
{
    public function handle(SignatureSent|SignatureCancelled|SignatureCompleted $event): void
    {
        [$name, $message] = match (true) {
            $event instanceof SignatureSent => ['firma_inviata', 'Documento inviato per la firma'],
            $event instanceof SignatureCancelled => ['firma_annullata', 'Richiesta di firma annullata'],
            default => ['firma_completata', 'Documento firmato'],
        };

        $logger = activity('firma')->performedOn($event->request)->event($name)->withProperties([
            'signature_request_id' => $event->request->getKey(),
            'provider' => $event->request->provider,
        ] + ($event instanceof SignatureCompleted ? ['document_id' => $event->document->getKey()] : []));

        if (property_exists($event, 'user') && $event->user !== null) {
            $logger->causedBy($event->user);
        }

        $logger->log($message);
    }
}

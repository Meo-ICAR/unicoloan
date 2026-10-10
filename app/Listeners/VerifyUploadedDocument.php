<?php

namespace App\Listeners;

use App\Models\Document;
use App\Services\DocumentVerifier;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Al caricamento di un file su un Document avvia i controlli automatici e lo mette in coda di verifica.
 */
class VerifyUploadedDocument
{
    public function __construct(private readonly DocumentVerifier $verifier) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $document = $event->media->model;

        if ($document instanceof Document && in_array($event->media->collection_name, ['documents', 'signed'], true)) {
            $this->verifier->check($document->fresh());
        }
    }
}

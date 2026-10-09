<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileSignatureRequest;
use App\Services\Signature\SignatureProviderManager;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Accetta solo i webhook del provider attivo (`signature.driver`); `fake` mai in produzione.
 * Dopo un cambio di driver i webhook del vecchio provider vengono rifiutati e le vecchie
 * richieste aperte sono coperte da `signature:sync`.
 */
class SignatureWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SignatureProviderManager $providers, SignatureRequestService $service): Response
    {
        if ($provider !== config('signature.driver') || ($provider === 'fake' && app()->isProduction())) {
            return response('', 404);
        }

        try {
            $signatureProvider = $providers->provider($provider);
        } catch (InvalidArgumentException) {
            return response('', 404);
        }

        $event = $signatureProvider->parseWebhook($request);

        if ($event === null) {
            return response('', 401);
        }

        if ($event->type === 'ignored') {
            return response('', 204);
        }

        $signatureRequest = $service->applyWebhook($provider, $event);

        if ($signatureRequest === null) {
            return response('', 204);
        }

        ReconcileSignatureRequest::dispatch($signatureRequest->id);

        return response('', 202);
    }
}

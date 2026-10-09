<?php

namespace App\Jobs;

use App\Models\SignatureRequest;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Riallinea una richiesta di firma allo stato presso il provider.
 */
class ReconcileSignatureRequest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 120;

    public function __construct(public int $signatureRequestId) {}

    public function uniqueId(): string
    {
        return (string) $this->signatureRequestId;
    }

    public function handle(SignatureRequestService $service): void
    {
        $request = SignatureRequest::query()->find($this->signatureRequestId);

        if ($request === null) {
            return;
        }

        $service->reconcile($request);
    }
}

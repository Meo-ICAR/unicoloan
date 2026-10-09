<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileSignatureRequest;
use App\Models\SignatureRequest;
use App\Services\Signature\FakeSignatureProvider;
use Illuminate\Console\Command;

class FakeCompleteSignature extends Command
{
    protected $signature = 'signature:fake-complete {ref : Riferimento della busta fake} {--decline : Rifiuta invece di firmare} {--expire : Fa scadere invece di firmare}';

    protected $description = 'Sviluppo: completa, rifiuta o fa scadere una busta del provider di firma fake e la riconcilia';

    public function handle(): int
    {
        if (config('signature.driver') !== 'fake' || app()->isProduction()) {
            $this->error('Comando disponibile solo con il provider di firma fake e fuori produzione.');

            return self::FAILURE;
        }

        $ref = (string) $this->argument('ref');
        $request = SignatureRequest::query()->where('provider', 'fake')->where('provider_ref', $ref)->first();

        if ($request === null) {
            $this->error('Richiesta di firma non trovata.');

            return self::FAILURE;
        }

        $fake = new FakeSignatureProvider;

        match (true) {
            (bool) $this->option('decline') => $fake->decline($ref),
            (bool) $this->option('expire') => $fake->expire($ref),
            default => $fake->completeAll($ref),
        };

        ReconcileSignatureRequest::dispatchSync($request->id);

        $this->info('Stato della richiesta: '.$request->refresh()->status->value);

        return self::SUCCESS;
    }
}

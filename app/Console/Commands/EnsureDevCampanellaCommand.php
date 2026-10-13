<?php

namespace App\Console\Commands;

use App\Support\Agenti\DevCampanellaIdentity;
use Illuminate\Console\Command;

class EnsureDevCampanellaCommand extends Command
{
    protected $signature = 'agenti:ensure-dev-campanella';

    protected $description = 'Predispone l\'identità dev Campanella (solo APP_ENV=local)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Comando consentito solo con APP_ENV=local.');

            return self::FAILURE;
        }

        $user = DevCampanellaIdentity::ensure();
        $identity = DevCampanellaIdentity::config();

        $this->info(sprintf(
            'Identità dev pronta: fornitore «%s» (P.IVA %s) collegato a user_id %d (%s).',
            $identity['fornitore_name'],
            $identity['piva'],
            $user->getKey(),
            $user->email,
        ));

        return self::SUCCESS;
    }
}

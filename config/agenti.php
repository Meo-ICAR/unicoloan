<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bypass dev-only per il portale agenti (Campanella)
    |--------------------------------------------------------------------------
    |
    | Attivo solo se APP_ENV=local e AGENTI_DEV_BYPASS=true. In testing, staging
    | e production il meccanismo risponde 404 anche con flag impostato.
    | Dopo l'abilitazione eseguire: php artisan agenti:ensure-dev-campanella
    |
    */

    'dev_bypass' => [
        'enabled' => filter_var(env('AGENTI_DEV_BYPASS', false), FILTER_VALIDATE_BOOL),

        'identity' => [
            'fornitore_name' => 'Campanella',
            'email' => 'campanella.dev@example.test',
            'piva' => '99988877701',
        ],
    ],

];

<?php

// Sostituisce per intero la sezione `access` del pacchetto (vedi vendor/meo-icar/unico-core/config/unico-core.php).
return [
    'access' => [
        'plan' => env('PLAN_TYPE'),
        'default_plan' => 'FULL',
        'trial_expired_plan' => 'BASE',
        'bypass_roles' => ['admin', 'super_admin'],
        // L'agente usa solo il portale /agenti, che non passa da queste funzionalità: senza questo blocco l'assenza di un profilo
        // Employee lo lascerebbe passare.
        'deny_roles' => ['agent'],
    ],
];

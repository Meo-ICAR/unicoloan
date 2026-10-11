<?php

/*
 * Comandi che un sistema esterno (UnicoBPM) può lanciare via API (bridge di unico-core: POST /api/commands/{comando}).
 * Solo quelli elencati, e solo con le opzioni indicate. L'esecuzione è in coda e ha un esito leggibile (GET /api/commands/runs/{id}).
 */
return [
    'documents:sync-schedule' => [
        'description' => 'Ricalcola lo scadenziario documenti.',
        'options' => [],
    ],
    'documents:send-reminders' => [
        'description' => 'Invia i solleciti dei documenti in scadenza o mancanti (con force, anche fuori dalle soglie).',
        'options' => ['force'],
    ],
    'signature:sync' => [
        'description' => 'Riallinea le richieste di firma aperte allo stato presso il provider.',
        'options' => ['limit', 'stale-minutes'],
    ],
];

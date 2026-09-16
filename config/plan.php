<?php

use App\Enums\PlanType;

return [

    /*
    |--------------------------------------------------------------------------
    | Piano / licenza attiva dell'applicazione
    |--------------------------------------------------------------------------
    |
    | Determina quali funzionalita' sono disponibili. Deve stare in un file di
    | configurazione (non letto con env() a runtime) altrimenti con
    | `php artisan config:cache` in produzione il valore diventa null e il
    | gating salta silenziosamente al piano di fallback.
    |
    */

    'type' => env('APP_PLAN', PlanType::Full->value),

];

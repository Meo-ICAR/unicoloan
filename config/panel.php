<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domini email abilitati all'accesso al pannello
    |--------------------------------------------------------------------------
    |
    | Elenco di domini (senza @) autorizzati ad accedere ai pannelli Filament.
    | Serve a contenere la registrazione libera via Socialite (Google/Microsoft).
    | Lasciare vuoto per mantenere il comportamento storico (tutti abilitati).
    | Esempio: PANEL_ALLOWED_EMAIL_DOMAINS="races.it,hassisto.com"
    |
    */

    'allowed_email_domains' => array_filter(
        array_map('trim', explode(',', (string) env('PANEL_ALLOWED_EMAIL_DOMAINS', '')))
    ),

];

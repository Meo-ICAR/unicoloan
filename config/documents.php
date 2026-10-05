<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Giorni preavviso default
    |--------------------------------------------------------------------------
    |
    | Usati quando il tipo documento non definisce notify_days_before.
    |
    */
    'default_notify_days_before' => [30, 15, 7, 1, 0],

    /*
    |--------------------------------------------------------------------------
    | Finestra scadenziario (giorni)
    |--------------------------------------------------------------------------
    |
    | Documenti con scadenza entro N giorni (o già scaduti) compaiono
    | nello scadenziario Filament.
    |
    */
    'schedule_window_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Email di fallback
    |--------------------------------------------------------------------------
    |
    | Destinatario quando l'entità collegata non ha un indirizzo email.
    |
    */
    'reminder_fallback_email' => env('DOCUMENT_REMINDER_FALLBACK_EMAIL'),

];

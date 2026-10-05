<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:send-reminders')->dailyAt('08:00');

// Rinfresca i dati di lavoro del semestrale OAM. L'import e' idempotente sul
// periodo corrente ed e' protetto da lock: ricostruisce solo company + periodo.
Schedule::command('oam:import-pratiche')
    ->weeklyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

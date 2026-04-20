<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stuur dagelijks om 08:00 de vervalmails voor lidmaatschappen.
// Gebruik --dry-run om te testen zonder effectief te verzenden:
//   php artisan memberships:send-expiry-mails --dry-run
Schedule::command('memberships:send-expiry-mails')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

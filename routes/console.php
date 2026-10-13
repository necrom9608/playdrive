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

// Dagtotalen automatisch naar de boekhouding (per tenant ingesteld: maand/kwartaal + verzenddag).
// Testen zonder te verzenden:
//   php artisan accounting:send-day-totals --dry-run --date=2027-01-05
Schedule::command('accounting:send-day-totals')
    ->dailyAt('07:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->runInBackground();

<?php

use App\Domain\Games\GameArtwork;
use App\Models\Game;
use App\Models\Setup;
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

// Mailassistent: nieuwe klantmails ophalen en concept-antwoorden opstellen (enkel tenants waar hij aan staat).
//   php artisan mail-assistant:fetch --tenant=1 --no-draft
Schedule::command('mail-assistant:fetch')
    ->everyTwoMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

// Banners voor Frontdesk → Zaal ophalen voor bestaande games (eigen uploads blijven staan).
//   php artisan games:banners --tenant=1          enkel games zonder banner die nog nooit gezocht werden
//   php artisan games:banners --tenant=1 --force  alles opnieuw (behalve uploads en bewust verwijderde)
Artisan::command('games:banners {--tenant=} {--force}', function (GameArtwork $artwork) {
    $force = (bool) $this->option('force');
    $games = Game::query()
        ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
        ->where('banner_manual', false)
        ->where(fn ($q) => $q->whereNull('banner_source')->orWhereNotIn('banner_source', ['upload', 'removed']))
        ->when(! $force, fn ($q) => $q->whereNull('banner_path')->whereNull('banner_fetched_at'))
        ->orderBy('id')
        ->get();

    $counts = [];
    foreach ($games as $game) {
        $status = $artwork->auto($game, $force);
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $this->line(sprintf('%-10s %s', $status, $game->name));
        usleep(200_000);
    }

    $this->info(collect($counts)->map(fn ($n, $k) => "{$k}: {$n}")->implode(' · ') ?: 'Niets te doen.');
})->purpose('Banners ophalen voor de Zaal-tegels');

// Banners voor speelplekken: arcadekasten krijgen hun marquee (MAME-romnaam wordt zelf gezocht),
// consoles/VR zonder Playscope een banner van hun eerste game. Zelf gekozen banners blijven staan.
//   php artisan setups:banners --tenant=1 [--force]
Artisan::command('setups:banners {--tenant=} {--force}', function (GameArtwork $artwork) {
    $force = (bool) $this->option('force');
    $setups = Setup::query()
        ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
        ->where('status', '!=', Setup::STATUS_ARCHIVED)
        ->where('banner_manual', false)
        ->where(fn ($q) => $q->whereNull('banner_source')->orWhereNotIn('banner_source', ['upload', 'removed']))
        ->when(! $force, fn ($q) => $q->whereNull('banner_path'))
        ->orderBy('id')
        ->get();

    $counts = [];
    foreach ($setups as $setup) {
        $status = $artwork->autoSetup($setup, $force);
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $this->line(sprintf('%-10s %s%s', $status, $setup->name, $setup->mame_name ? " [{$setup->mame_name}]" : ''));
    }

    $this->info(collect($counts)->map(fn ($n, $k) => "{$k}: {$n}")->implode(' · ') ?: 'Niets te doen.');
})->purpose('Banners zoeken voor de speelplekken (Zaal-tegels)');

// Elke nacht: banners voor nieuwe games en speelplekken zonder banner.
Schedule::command('games:banners')->dailyAt('04:20')->timezone('Europe/Brussels')->withoutOverlapping()->runInBackground();
Schedule::command('setups:banners')->dailyAt('04:40')->timezone('Europe/Brussels')->withoutOverlapping()->runInBackground();

<?php

use App\Http\Controllers\Website\VenuePageController;
use Illuminate\Support\Facades\Route;

/*
 * Website module — publiek toegankelijk op /
 *
 * Volgorde matters:
 *   1. Specifieke publieke routes (zoals /venues/{slug}) eerst
 *   2. Catch-all '/' en '/{any}' laatste, zodat de Vue router enkel triggert
 *      voor URL's die hierboven niet gematcht zijn.
 *
 * /reserveren/:tenant wordt client-side afgehandeld door Vue router.
 */

// Publieke venuepagina — server-rendered Blade voor SEO
Route::get('/venues/{slug}', [VenuePageController::class, 'show'])
    ->name('venue.show');

// Vue website-app catch-all
Route::get('/', fn () => view('website.app'));
Route::get('/{any}', fn () => view('website.app'))
    ->where('any', '^(?!member|frontdesk|backoffice|api|member-api|staff|kiosk|display|admin|portal|venues|_devframe|up).*');

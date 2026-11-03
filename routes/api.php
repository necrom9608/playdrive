<?php

use App\Http\Controllers\Api\Arcade\ArcadeSyncController;
use App\Http\Controllers\Api\Device\DeviceCommandController;
use App\Http\Controllers\Api\Device\DeviceController;
use App\Http\Controllers\Api\Device\DeviceGameController;
use App\Http\Controllers\Api\Device\DevicePairingController;
use App\Http\Controllers\Api\Device\DeviceRotationController;
use App\Http\Controllers\Api\Device\DeviceSessionController;
use App\Http\Controllers\Api\Public\AccountRegistrationController;
use App\Http\Controllers\Api\Public\BookingFormSetupController;
use App\Http\Controllers\Api\Public\PlayscopeReleaseController;
use App\Http\Controllers\Api\Public\PublicReservationController;
use App\Http\Controllers\Api\PublicApi\PublicSubmissionController;
use App\Http\Controllers\Api\ResendWebhookController;
use App\Http\Controllers\Website\SetupLandingController;
use Illuminate\Support\Facades\Route;

// Externe, stateless API-routes komen hier.
// Interne PlayDrive app-endpoints met sessies draaien via web-routes
// in routes/frontdesk-api.php en routes/backoffice-api.php.

// Routes met API key — voor externe integraties
Route::prefix('public')->middleware('public.api')->group(function () {
    Route::post('/reservations', [PublicSubmissionController::class, 'storeReservation']);
    Route::post('/gift-vouchers', [PublicSubmissionController::class, 'storeGiftVoucher']);
});

// Booking form routes — geen API key, publiek toegankelijk via eigen website
// Enkel rate limiting als bescherming
Route::prefix('public')->middleware('throttle:60,1')->group(function () {
    Route::get('/booking-form/setup', BookingFormSetupController::class);
    Route::post('/booking-form/submit', [PublicSubmissionController::class, 'storeReservation']);
    Route::get('/reservatie/{token}', [PublicReservationController::class, 'show']);
});

// Publieke registratie — geen API key vereist, enkel rate limiting
Route::prefix('register')->middleware('throttle:10,1')->group(function () {
    Route::get('/{tenantSlug}', [AccountRegistrationController::class, 'tenantInfo']);
    Route::post('/{tenantSlug}', [AccountRegistrationController::class, 'store']);
    Route::post('/resend-verification', [AccountRegistrationController::class, 'resendVerification']);
});

// E-mailverificatie link — redirect naar client app na bevestiging
Route::get('/register/verify/{token}', [AccountRegistrationController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('account.verify');

// Resend webhook — geen auth, wel throttle
Route::post('/webhooks/resend', ResendWebhookController::class)->middleware('throttle:120,1');

// Arcade-scores: playdrive-sync op de rhythm-server van een venue (Bearer-token per bron)
//   token aanmaken: php artisan arcade:source <tenant> "<naam>"
Route::post('/arcade/v1/sync', ArcadeSyncController::class)->middleware(['arcade.source', 'throttle:120,1']);

// Speelplek-info voor de QR-code (member-app) — publiek, enkel rate limiting
Route::get('/public/setups/{code}', [SetupLandingController::class, 'json'])->middleware('throttle:60,1');

// Playscope installeren en bijwerken (publiek, releases gebouwd via Playmix Ops)
//   installeren op een pc: curl -fsSL <APP_URL>/api/playscope/install.sh | bash
//   latest.json?platform=windows → de Windows-build (zip)
Route::prefix('playscope')->middleware('throttle:120,1')->group(function () {
    Route::get('/latest.json', [PlayscopeReleaseController::class, 'latest']);
    Route::get('/install.sh', [PlayscopeReleaseController::class, 'install']);
    Route::get('/download/{version}', [PlayscopeReleaseController::class, 'download'])->where('version', '[0-9]+\.[0-9]+\.[0-9]+');
    // Windows (racers, TeknoParrot): installeren in PowerShell: irm <APP_URL>/api/playscope/install.ps1 | iex
    Route::get('/install.ps1', [PlayscopeReleaseController::class, 'installWindows']);
    Route::get('/download/{version}/{platform}', [PlayscopeReleaseController::class, 'download'])
        ->where(['version' => '[0-9]+\.[0-9]+\.[0-9]+', 'platform' => 'linux|windows']);
});

// Onbemande toestellen (kiosk, Playscope)
//   koppelen: toestel toont code → backoffice Speelplekken → Toestellen → Toestel koppelen
Route::prefix('device/v1')->group(function () {
    Route::post('/pair/start', [DevicePairingController::class, 'start'])->middleware('throttle:30,1');
    Route::post('/pair/poll', [DevicePairingController::class, 'poll'])->middleware('throttle:600,1');   // per IP: alle PC's achter één NAT

    // per IP: alle Playscope-pc's van een venue zitten achter één NAT en halen elke 5 s opdrachten op
    Route::middleware(['venue.device', 'throttle:1500,1'])->group(function () {
        Route::get('/me', [DeviceController::class, 'me']);
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);

        // Playscope: geïnstalleerde games melden en de indeling (categorieën, spelers) ophalen
        Route::get('/games', [DeviceGameController::class, 'show']);
        Route::post('/games', [DeviceGameController::class, 'store']);

        // Bediening op afstand: opdrachten ophalen (Playscope: elke 5 s) en het resultaat melden
        Route::get('/commands', [DeviceCommandController::class, 'index']);
        Route::post('/commands/{command}', [DeviceCommandController::class, 'ack'])->whereNumber('command');

        // Speelbeurten (statistieken): Playscope stuurt afgesloten beurten door
        Route::post('/sessions', [DeviceSessionController::class, 'store']);

        // Rotatie (toestel in een groep): melden hoe lang geen input, horen welke game moet draaien
        Route::post('/rotation', [DeviceRotationController::class, 'poll']);
        // Kioskscherm boven een groep: huidig spel, stoelen, programma
        Route::get('/group-display', [DeviceRotationController::class, 'display']);
    });
});

<?php

use App\Http\Controllers\Api\Arcade\ArcadeSyncController;
use App\Http\Controllers\Api\Device\DeviceController;
use App\Http\Controllers\Api\Device\DevicePairingController;
use App\Http\Controllers\Api\Public\AccountRegistrationController;
use App\Http\Controllers\Api\Public\BookingFormSetupController;
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

// Onbemande toestellen (kiosk, Playscope)
//   koppelen: toestel toont code → backoffice Speelplekken → Toestellen → Toestel koppelen
Route::prefix('device/v1')->group(function () {
    Route::post('/pair/start', [DevicePairingController::class, 'start'])->middleware('throttle:30,1');
    Route::post('/pair/poll', [DevicePairingController::class, 'poll'])->middleware('throttle:600,1');   // per IP: alle PC's achter één NAT

    Route::middleware(['venue.device', 'throttle:600,1'])->group(function () {
        Route::get('/me', [DeviceController::class, 'me']);
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);
    });
});

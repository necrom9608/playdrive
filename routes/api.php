<?php

use App\Http\Controllers\Api\Public\AccountRegistrationController;
use App\Http\Controllers\Api\PublicApi\PublicSubmissionController;
use App\Http\Controllers\Api\ResendWebhookController;
use Illuminate\Support\Facades\Route;

// Externe, stateless API-routes komen hier.
// Interne PlayDrive app-endpoints met sessies draaien via web-routes
// in routes/frontdesk-api.php en routes/backoffice-api.php.

Route::prefix('public')->middleware('public.api')->group(function () {
    Route::post('/reservations', [PublicSubmissionController::class, 'storeReservation']);
    Route::post('/gift-vouchers', [PublicSubmissionController::class, 'storeGiftVoucher']);
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

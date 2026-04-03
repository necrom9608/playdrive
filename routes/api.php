<?php

use App\Http\Controllers\Api\PublicApi\PublicSubmissionController;
use Illuminate\Support\Facades\Route;

// Externe, stateless API-routes komen hier.
// Interne PlayDrive app-endpoints met sessies draaien via web-routes
// in routes/frontdesk-api.php en routes/backoffice-api.php.

Route::prefix('public')->middleware('public.api')->group(function () {
    Route::post('/reservations', [PublicSubmissionController::class, 'storeReservation']);
    Route::post('/gift-vouchers', [PublicSubmissionController::class, 'storeGiftVoucher']);
});

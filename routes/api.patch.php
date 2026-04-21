<?php

// booking-form v3
// ─────────────────────────────────────────────────────────────────────────────
// Voeg deze regels toe aan routes/api.php
//
// Bovenaan bij de andere use-statements:
//   use App\Http\Controllers\Api\Public\BookingFormSetupController;
//
// Binnen het Route::prefix('public')->middleware('public.api')->group(...) blok,
// na de bestaande reservations en gift-vouchers routes:
//
//   Route::get('/booking-form/setup', BookingFormSetupController::class);
// ─────────────────────────────────────────────────────────────────────────────

use App\Http\Controllers\Api\Public\BookingFormSetupController;

// Binnen public middleware groep:
Route::get('/booking-form/setup', BookingFormSetupController::class);

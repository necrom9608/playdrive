<?php

use App\Http\Controllers\Api\Member\MemberAuthController;
use App\Http\Controllers\Api\Member\MemberProfileController;
use App\Http\Controllers\Api\Member\MemberReservationController;
use App\Http\Controllers\Api\Member\MemberVenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('member-api/v1')->group(function () {

    // Publieke endpoints — geen authenticatie vereist
    Route::post('/auth/register',        [MemberAuthController::class, 'register']);
    Route::post('/auth/login',           [MemberAuthController::class, 'login']);
    Route::post('/auth/forgot-password', [MemberAuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password',  [MemberAuthController::class, 'resetPassword']);

    // Venue discovery — publiek
    Route::get('/venues/discover',       [MemberVenueController::class, 'discover']);
    Route::get('/venues/{slug}',         [MemberVenueController::class, 'show']);

    // Beveiligde endpoints — Sanctum token vereist
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me',                        [MemberAuthController::class, 'me']);
        Route::post('/auth/logout',                   [MemberAuthController::class, 'logout']);

        Route::put('/profile',                        [MemberProfileController::class, 'update']);

        Route::get('/venues',                         [MemberVenueController::class, 'index']);
        Route::post('/venues/{slug}/join',            [MemberVenueController::class, 'join']);
        Route::get('/venues/{slug}/membership',       [MemberVenueController::class, 'membership']);

        Route::get('/reservations',                   [MemberReservationController::class, 'index']);
        Route::get('/reservations/{id}',              [MemberReservationController::class, 'show']);
    });
});

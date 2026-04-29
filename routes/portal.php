<?php

use App\Http\Controllers\Api\Portal\AuthController;
use App\Http\Controllers\Api\Portal\VenueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal routes — bereikbaar op /portal/*
|
| Voor venue-uitbaters die hun publieke pagina beheren.
| Aparte sessie ('portal_auth') zodat het niet conflicteert met Backoffice.
|--------------------------------------------------------------------------
*/

Route::prefix('portal')->group(function () {

    // ------------------------------------------------------------------
    // API — publiek (geen auth vereist)
    // ------------------------------------------------------------------
    Route::prefix('api')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login'])->name('portal.api.login');
    });

    // ------------------------------------------------------------------
    // API — beveiligd
    // ------------------------------------------------------------------
    Route::prefix('api')
        ->middleware('portal.auth')
        ->group(function () {

            // Auth
            Route::get('/auth/me', [AuthController::class, 'me'])->name('portal.api.me');
            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('portal.api.logout');

            // Info — naam, adres, contact, doelgroep
            Route::get('/venue/info', [VenueController::class, 'getInfo']);
            Route::put('/venue/info', [VenueController::class, 'updateInfo']);

            // Media — logo, hero, foto's, video
            Route::get('/venue/media', [VenueController::class, 'getMedia']);
            Route::post('/venue/media/logo', [VenueController::class, 'uploadLogo']);
            Route::delete('/venue/media/logo', [VenueController::class, 'deleteLogo']);
            Route::post('/venue/media/hero', [VenueController::class, 'uploadHero']);
            Route::delete('/venue/media/hero', [VenueController::class, 'deleteHero']);
            Route::post('/venue/media/photos', [VenueController::class, 'uploadPhoto']);
            Route::put('/venue/media/photos/order', [VenueController::class, 'reorderPhotos']);
            Route::delete('/venue/media/photos/{photo}', [VenueController::class, 'deletePhoto']);

            // Activities
            Route::get('/venue/activities', [VenueController::class, 'getActivities']);
            Route::post('/venue/activities', [VenueController::class, 'createActivity']);
            Route::put('/venue/activities/order', [VenueController::class, 'reorderActivities']);
            Route::put('/venue/activities/{activity}', [VenueController::class, 'updateActivity']);
            Route::delete('/venue/activities/{activity}', [VenueController::class, 'deleteActivity']);

            // Amenities
            Route::get('/venue/amenities', [VenueController::class, 'getAmenities']);
            Route::put('/venue/amenities', [VenueController::class, 'updateAmenities']);

            // Links
            Route::get('/venue/links', [VenueController::class, 'getLinks']);
            Route::post('/venue/links', [VenueController::class, 'createLink']);
            Route::put('/venue/links/{link}', [VenueController::class, 'updateLink']);
            Route::delete('/venue/links/{link}', [VenueController::class, 'deleteLink']);

            // Publication
            Route::get('/venue/publication', [VenueController::class, 'getPublication']);
            Route::put('/venue/publication/slug', [VenueController::class, 'updateSlug']);
            Route::post('/venue/publication/publish', [VenueController::class, 'publish']);
            Route::post('/venue/publication/unpublish', [VenueController::class, 'unpublish']);
        });

    // ------------------------------------------------------------------
    // Vue SPA catch-all — moet NA de API-routes staan
    // ------------------------------------------------------------------
    Route::get('/{any?}', fn () => view('portal.app'))
        ->where('any', '.*')
        ->name('portal.app');
});

<?php

use Illuminate\Support\Facades\Route;

// Dev frame — enkel buiten productie beschikbaar
if (! app()->isProduction()) {
    Route::get('/_devframe/member', fn () => view('devframe.member'))->name('devframe.member');
}

// PWA manifest routes met correcte Content-Type header
Route::get('/staff.webmanifest', function () {
    return response()->file(public_path('staff.webmanifest'), [
        'Content-Type' => 'application/manifest+json',
    ]);
});

require base_path('routes/frontdesk-api.php');
require base_path('routes/backoffice-api.php');
require base_path('routes/staff-api.php');
require base_path('routes/display-api.php');

Route::prefix('frontdesk')->group(base_path('routes/frontdesk.php'));
Route::prefix('backoffice')->group(base_path('routes/backoffice.php'));
Route::prefix('kiosk')->group(base_path('routes/kiosk.php'));
Route::prefix('client')->group(base_path('routes/client.php'));
Route::prefix('staff')->group(base_path('routes/staff.php'));
Route::prefix('display')->group(base_path('routes/display.php'));

require base_path('routes/admin.php');
require base_path('routes/portal.php');

// QR-code op een speelplek (arcadekast, LAN-PC, ...) — vóór de website catch-all
Route::get('/s/{code}', [\App\Http\Controllers\Website\SetupLandingController::class, 'show'])
    ->where('code', '[a-z0-9]{4,12}')
    ->middleware('throttle:60,1')
    ->name('setup.show');

// Aankoopborderel: de verkoper (particulier) vult in en ondertekent — vóór de website catch-all
$purchaseToken = '[A-Za-z0-9]{32,64}';
Route::get('/borderel/{token}', [\App\Http\Controllers\Website\PurchaseSignController::class, 'show'])
    ->where('token', $purchaseToken)
    ->middleware('throttle:purchase-sign-view')
    ->name('purchase.sign');
Route::post('/borderel/{token}', [\App\Http\Controllers\Website\PurchaseSignController::class, 'submit'])
    ->where('token', $purchaseToken)
    ->middleware('throttle:purchase-sign-submit');
Route::get('/borderel/{token}/pdf', [\App\Http\Controllers\Website\PurchaseSignController::class, 'pdf'])
    ->where('token', $purchaseToken)
    ->middleware('throttle:purchase-sign-files');
Route::get('/borderel/{token}/photos/{index}', [\App\Http\Controllers\Website\PurchaseSignController::class, 'photo'])
    ->where('token', $purchaseToken)
    ->whereNumber('index')
    ->middleware('throttle:purchase-sign-files');

// Banners van games/speelplekken (Frontdesk → Zaal); ?v=<hash> wisselt bij een nieuw beeld.
Route::get('/media/banners/{kind}/{id}', [\App\Http\Controllers\Website\BannerMediaController::class, 'show'])
    ->whereIn('kind', ['game', 'setup'])
    ->whereNumber('id')
    ->middleware('throttle:600,1');

// Website — catch-all op / — moet als laatste staan
Route::group([], base_path('routes/website.php'));

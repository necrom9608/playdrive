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

// Website — catch-all op / — moet als laatste staan
Route::group([], base_path('routes/website.php'));

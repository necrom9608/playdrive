<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/frontdesk');

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

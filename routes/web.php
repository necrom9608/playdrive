<?php

use Illuminate\Support\Facades\Route;

Route::domain('frontdesk.playdrive.test')->group(function () {
    require base_path('routes/frontdesk.php');
});

Route::domain('backoffice.playdrive.test')->group(function () {
    require base_path('routes/backoffice.php');
});

Route::domain('kiosk.playdrive.test')->group(function () {
    require base_path('routes/kiosk.php');
});

Route::domain('client.playdrive.test')->group(function () {
    require base_path('routes/client.php');
});

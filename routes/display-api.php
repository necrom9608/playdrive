<?php

use App\Http\Controllers\Api\Display\DisplayDeviceController;
use App\Http\Controllers\Api\Display\DisplaySyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/display')->group(function () {
    Route::post('/bootstrap', [DisplayDeviceController::class, 'bootstrap']);
    Route::get('/state', [DisplayDeviceController::class, 'state']);

    Route::middleware('frontdesk.auth')->group(function () {
        Route::post('/sync', [DisplaySyncController::class, 'sync']);
    });
});

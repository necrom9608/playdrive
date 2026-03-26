<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Frontdesk\FormOptionsController;
use App\Http\Controllers\Api\Frontdesk\LocationSearchController;
use App\Http\Controllers\Api\Frontdesk\RegistrationController;

Route::view('/', 'frontdesk.app');
Route::view('/pos', 'frontdesk.app');
Route::view('/sales', 'frontdesk.app');
Route::view('/vouchers', 'frontdesk.app');
Route::view('/agenda', 'frontdesk.app');
Route::view('/staff', 'frontdesk.app');
Route::view('/members', 'frontdesk.app');
Route::view('/tasks', 'frontdesk.app');


Route::prefix('api/frontdesk')->group(function () {
    Route::get('/form-options', FormOptionsController::class);
    Route::get('/locations/search', LocationSearchController::class);
});

Route::prefix('api/frontdesk')->group(function () {
    Route::get('/form-options', FormOptionsController::class);
    Route::get('/locations/search', LocationSearchController::class);

    Route::get('/registrations', [RegistrationController::class, 'index']);
    Route::post('/registrations', [RegistrationController::class, 'store']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Frontdesk\FormOptionsController;
use App\Http\Controllers\Api\Frontdesk\LocationSearchController;
use App\Http\Controllers\Api\Frontdesk\RegistrationController;
use App\Http\Controllers\Api\Frontdesk\OrderController;
use App\Http\Controllers\Api\Frontdesk\SalesController;
use App\Http\Controllers\Api\Frontdesk\AgendaController;

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

    Route::get('/registrations', [RegistrationController::class, 'index']);
    Route::post('/registrations', [RegistrationController::class, 'store']);
    Route::put('/registrations/{registration}', [RegistrationController::class, 'update']);

    Route::post('/registrations/{registration}/check-in', [RegistrationController::class, 'checkIn']);
    Route::post('/registrations/{registration}/check-out', [RegistrationController::class, 'checkOut']);
    Route::post('/registrations/{registration}/cancel', [RegistrationController::class, 'cancel']);
    Route::post('/registrations/{registration}/no-show', [RegistrationController::class, 'noShow']);
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);

    Route::post('/orders/checkout', [OrderController::class, 'checkout']);

    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/agenda', AgendaController::class);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);
});

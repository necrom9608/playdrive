<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Backoffice\CateringOptionController;
use App\Http\Controllers\Api\Backoffice\CateringOptionProductController;

Route::view('/', 'backoffice.app');
Route::view('/products', 'backoffice.app');
Route::view('/catering-options', 'backoffice.app');
Route::view('/event-types', 'backoffice.app');
Route::view('/stay-options', 'backoffice.app');
Route::view('/staff', 'backoffice.app');
Route::view('/pricing-engine', 'backoffice.app');

Route::prefix('backoffice')->group(function () {
    Route::get('/catering-options', [CateringOptionController::class, 'index']);
    Route::post('/catering-options', [CateringOptionController::class, 'store']);
    Route::put('/catering-options/{cateringOption}', [CateringOptionController::class, 'update']);
    Route::delete('/catering-options/{cateringOption}', [CateringOptionController::class, 'destroy']);
    Route::post('/catering-options/reorder', [CateringOptionController::class, 'reorder']);

    Route::get('/catering-options/{cateringOption}/products', [CateringOptionProductController::class, 'index']);
    Route::post('/catering-options/{cateringOption}/products', [CateringOptionProductController::class, 'store']);
    Route::post('/catering-options/{cateringOption}/products/reorder', [CateringOptionProductController::class, 'reorder']);

    Route::put('/catering-option-products/{cateringOptionProduct}', [CateringOptionProductController::class, 'update']);
    Route::delete('/catering-option-products/{cateringOptionProduct}', [CateringOptionProductController::class, 'destroy']);
});

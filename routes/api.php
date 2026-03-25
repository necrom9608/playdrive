<?php

use App\Http\Controllers\Api\Backoffice\ProductCategoryController;
use App\Http\Controllers\Api\Backoffice\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('backoffice')->group(function () {
    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::post('/product-categories', [ProductCategoryController::class, 'store']);
    Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
});

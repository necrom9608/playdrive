<?php

use App\Http\Controllers\Api\Backoffice\OptionController;
use App\Http\Controllers\Api\Backoffice\ProductCategoryController;
use App\Http\Controllers\Api\Backoffice\ProductController;
use App\Http\Controllers\Api\Backoffice\PricingEngineController;
use App\Http\Controllers\Api\Backoffice\StaffController;
use App\Http\Controllers\Api\Frontdesk\PricingController;
use Illuminate\Support\Facades\Route;

Route::prefix('backoffice')->group(function () {
    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::post('/product-categories', [ProductCategoryController::class, 'store']);
    Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
    Route::post('/product-categories/reorder', [ProductCategoryController::class, 'reorder']);
    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::post('/products/reorder', [ProductController::class, 'reorder']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::get('/options/{type}', [OptionController::class, 'index']);
    Route::post('/options/{type}', [OptionController::class, 'store']);
    Route::put('/options/{type}/{item}', [OptionController::class, 'update']);
    Route::post('/options/{type}/reorder', [OptionController::class, 'reorder']);
    Route::delete('/options/{type}/{item}', [OptionController::class, 'destroy']);

    Route::get('/pricing-engine', [PricingEngineController::class, 'overview']);
    Route::post('/pricing-engine/profiles', [PricingEngineController::class, 'storeProfile']);
    Route::put('/pricing-engine/profiles/{profile}', [PricingEngineController::class, 'updateProfile']);
    Route::delete('/pricing-engine/profiles/{profile}', [PricingEngineController::class, 'deleteProfile']);
    Route::post('/pricing-engine/profiles/reorder', [PricingEngineController::class, 'reorderProfiles']);

    Route::post('/pricing-engine/profiles/{profile}/rules', [PricingEngineController::class, 'storeRule']);
    Route::post('/pricing-engine/profiles/{profile}/rules/reorder', [PricingEngineController::class, 'reorderRules']);
    Route::put('/pricing-engine/rules/{rule}', [PricingEngineController::class, 'updateRule']);
    Route::delete('/pricing-engine/rules/{rule}', [PricingEngineController::class, 'deleteRule']);

    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::post('/staff/reorder', [StaffController::class, 'reorder']);
    Route::put('/staff/{staff}', [StaffController::class, 'update']);
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
});

Route::prefix('frontdesk')->group(function () {
    Route::post('/pricing/evaluate', [PricingController::class, 'evaluate']);
});

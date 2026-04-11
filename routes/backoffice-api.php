<?php

use App\Http\Controllers\Api\Backoffice\AuthController;
use App\Http\Controllers\Api\Backoffice\BadgeTemplateController;
use App\Http\Controllers\Api\Backoffice\CateringOptionController;
use App\Http\Controllers\Api\Backoffice\CateringOptionProductController;
use App\Http\Controllers\Api\Backoffice\OptionController;
use App\Http\Controllers\Api\Backoffice\PricingEngineController;
use App\Http\Controllers\Api\Backoffice\ProductCategoryController;
use App\Http\Controllers\Api\Backoffice\ProductController;
use App\Http\Controllers\Api\Backoffice\StaffController;
use App\Http\Controllers\Api\Backoffice\DeviceManagementController;
use App\Http\Controllers\Api\Backoffice\AnalyticsController;
use App\Http\Controllers\Api\Backoffice\DayTotalsController;
use App\Http\Controllers\Api\Backoffice\VoucherTemplateController;
use App\Http\Controllers\Api\Backoffice\PhysicalCardController;
use App\Http\Controllers\Api\Backoffice\StaffAttendanceManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/backoffice')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('backoffice.auth')->group(function () {
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

        Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/analytics/reporting', [AnalyticsController::class, 'reporting']);
        Route::get('/day-totals', [DayTotalsController::class, 'index']);
        Route::get('/day-totals/export', [DayTotalsController::class, 'export']);

        Route::get('/badge-templates', [BadgeTemplateController::class, 'index']);
        Route::post('/badge-templates', [BadgeTemplateController::class, 'store']);
        Route::post('/badge-templates/media', [BadgeTemplateController::class, 'uploadMedia']);
        Route::put('/badge-templates/{badgeTemplate}', [BadgeTemplateController::class, 'update']);
        Route::delete('/badge-templates/{badgeTemplate}', [BadgeTemplateController::class, 'destroy']);

        Route::get('/devices', [DeviceManagementController::class, 'index']);
        Route::post('/devices/pair', [DeviceManagementController::class, 'pair']);
        Route::post('/devices/{posDevice}/unpair', [DeviceManagementController::class, 'unpair']);
        Route::put('/devices/pos/{posDevice}', [DeviceManagementController::class, 'updatePos']);
        Route::put('/devices/display/{displayDevice}', [DeviceManagementController::class, 'updateDisplay']);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::post('/staff/reorder', [StaffController::class, 'reorder']);
        Route::put('/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);

        Route::get('/staff-attendance', [StaffAttendanceManagementController::class, 'index']);
        Route::put('/staff-attendance/{staffAttendance}', [StaffAttendanceManagementController::class, 'update']);
        Route::delete('/staff-attendance/{staffAttendance}', [StaffAttendanceManagementController::class, 'destroy']);

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

        Route::get('/badge-templates', [BadgeTemplateController::class, 'index']);
        Route::post('/badge-templates', [BadgeTemplateController::class, 'store']);
        Route::post('/badge-templates/media', [BadgeTemplateController::class, 'uploadMedia']);
        Route::put('/badge-templates/{badgeTemplate}', [BadgeTemplateController::class, 'update']);
        Route::delete('/badge-templates/{badgeTemplate}', [BadgeTemplateController::class, 'destroy']);

        Route::get('/voucher-templates', [VoucherTemplateController::class, 'index']);
        Route::post('/voucher-templates', [VoucherTemplateController::class, 'store']);
        Route::put('/voucher-templates/{voucherTemplate}', [VoucherTemplateController::class, 'update']);
        Route::delete('/voucher-templates/{voucherTemplate}', [VoucherTemplateController::class, 'destroy']);

        Route::get('/cards', [PhysicalCardController::class, 'index']);
        Route::post('/cards', [PhysicalCardController::class, 'store']);
        Route::put('/cards/{card}', [PhysicalCardController::class, 'update']);
        Route::delete('/cards/{card}', [PhysicalCardController::class, 'destroy']);
        Route::post('/cards/{card}/render-image', [PhysicalCardController::class, 'uploadRenderImage']);
        Route::post('/cards/{card}/mark-printed', [PhysicalCardController::class, 'markPrinted']);
        Route::get('/cards/{card}/pdf', [PhysicalCardController::class, 'pdf']);
    });
});

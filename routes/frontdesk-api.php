<?php

use App\Http\Controllers\Api\Display\DeviceController as DisplayDeviceController;
use App\Http\Controllers\Api\Frontdesk\AgendaController;
use App\Http\Controllers\Api\Frontdesk\AuthController;
use App\Http\Controllers\Api\Frontdesk\CatalogController;
use App\Http\Controllers\Api\Frontdesk\FormOptionsController;
use App\Http\Controllers\Api\Frontdesk\GiftVoucherController;
use App\Http\Controllers\Api\Frontdesk\LocationSearchController;
use App\Http\Controllers\Api\Frontdesk\MemberController;
use App\Http\Controllers\Api\Frontdesk\OrderController;
use App\Http\Controllers\Api\Frontdesk\PhysicalCardController;
use App\Http\Controllers\Api\Frontdesk\PricingController;
use App\Http\Controllers\Api\Frontdesk\RegistrationController;
use App\Http\Controllers\Api\Frontdesk\SalesController;
use App\Http\Controllers\Api\Frontdesk\StaffAttendanceController;
use App\Http\Controllers\Api\Frontdesk\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/display')->group(function () {
    Route::post('/bootstrap', [DisplayDeviceController::class, 'bootstrap']);
    Route::get('/state', [DisplayDeviceController::class, 'state']);
});

Route::prefix('api/frontdesk')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/login-card', [AuthController::class, 'loginWithCard']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/pricing/evaluate', [PricingController::class, 'evaluate']);

    Route::middleware('frontdesk.auth')->group(function () {
        Route::get('/form-options', FormOptionsController::class);
        Route::get('/locations/search', LocationSearchController::class);

        Route::get('/catalog/product-categories', [CatalogController::class, 'categories']);
        Route::get('/catalog/products', [CatalogController::class, 'products']);

        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::post('/registrations', [RegistrationController::class, 'store']);
        Route::put('/registrations/{registration}', [RegistrationController::class, 'update']);

        Route::post('/registrations/{registration}/check-in', [RegistrationController::class, 'checkIn']);
        Route::post('/registrations/{registration}/check-out', [RegistrationController::class, 'checkOut']);
        Route::post('/registrations/{registration}/cancel', [RegistrationController::class, 'cancel']);
        Route::post('/registrations/{registration}/no-show', [RegistrationController::class, 'noShow']);
        Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders/items', [OrderController::class, 'addItem']);
        Route::patch('/orders/{order}/items/{item}', [OrderController::class, 'updateItem']);
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'deleteItem']);
        Route::post('/orders/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{order}/refund', [OrderController::class, 'refund']);

        Route::post('/vouchers/validate', [GiftVoucherController::class, 'validateForPos']);
        Route::get('/vouchers', [GiftVoucherController::class, 'index']);
        Route::post('/vouchers', [GiftVoucherController::class, 'store']);
        Route::put('/vouchers/{voucher}', [GiftVoucherController::class, 'update']);


        Route::post('/display/sync', [DisplayDeviceController::class, 'sync']);

        Route::get('/sales', [SalesController::class, 'index']);
        Route::get('/agenda', AgendaController::class);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members/attendance/toggle', [MemberController::class, 'toggleAttendance']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::put('/members/{member}', [MemberController::class, 'update']);
        Route::post('/members/{member}/renew', [MemberController::class, 'renew']);
        Route::post('/members/{member}/send-email', [MemberController::class, 'sendEmail']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);

        Route::get('/staff-attendance', [StaffAttendanceController::class, 'index']);
        Route::post('/staff-attendance/scan', [StaffAttendanceController::class, 'scan']);
    });
});

<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware('playdrive.admin.host')
    ->group(function () {
        Route::redirect('/', '/admin/tenants');
        Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
        Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');

        Route::middleware('playdrive.admin.auth')->group(function () {
            Route::get('/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
            Route::post('/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
            Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
            Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('admin.tenants.destroy');
            Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
        });
    });

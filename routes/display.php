<?php

use App\Http\Controllers\Display\TenantLogoController;
use Illuminate\Support\Facades\Route;

Route::get('/tenant-logo', TenantLogoController::class)->name('display.tenant-logo');
Route::view('/{any?}', 'display.app')->where('any', '.*');

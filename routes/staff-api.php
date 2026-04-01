<?php

use App\Http\Controllers\Api\Staff\AgendaController;
use App\Http\Controllers\Api\Staff\AttendanceController;
use App\Http\Controllers\Api\Staff\AuthController;
use App\Http\Controllers\Api\Staff\DashboardController;
use App\Http\Controllers\Api\Staff\ProfileController;
use App\Http\Controllers\Api\Staff\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/staff')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('staff.auth')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::post('/attendance/toggle', [AttendanceController::class, 'toggle']);
        Route::get('/agenda', AgendaController::class);
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
    });
});

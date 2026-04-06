<?php

use App\Http\Controllers\Frontdesk\PhysicalCardPrintController;
use Illuminate\Support\Facades\Route;

Route::middleware('frontdesk.auth')->get('/cards/{card}/print', [PhysicalCardPrintController::class, 'show']);
Route::middleware('frontdesk.auth')->get('/cards/{card}/pdf', [\App\Http\Controllers\Api\Frontdesk\PhysicalCardController::class, 'pdf']);
Route::view('/{any?}', 'frontdesk.app')->where('any', '.*');

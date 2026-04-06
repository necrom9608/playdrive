<?php

use App\Http\Controllers\Frontdesk\PhysicalCardPrintController;
use Illuminate\Support\Facades\Route;

Route::middleware('backoffice.auth')->get('/cards/{card}/print', [PhysicalCardPrintController::class, 'show']);
Route::view('/{any?}', 'backoffice.app')->where('any', '.*');

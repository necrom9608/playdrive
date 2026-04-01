<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'staff.app')->where('any', '.*');

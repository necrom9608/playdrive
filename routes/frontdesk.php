<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'frontdesk.app')->where('any', '.*');

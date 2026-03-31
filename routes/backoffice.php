<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'backoffice.app')->where('any', '.*');

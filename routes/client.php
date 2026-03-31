<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'client.app')->where('any', '.*');

<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'member.app')->where('any', '.*');

<?php

use Illuminate\Support\Facades\Route;

/*
 * Website module — publiek toegankelijk op /
 * Catch-all zodat Vue router de client-side routing afhandelt.
 */
Route::get('/', fn () => view('website.app'));
Route::get('/{any}', fn () => view('website.app'))->where('any', '^(?!member|frontdesk|backoffice|api|member-api|staff|kiosk|display|admin|_devframe|up).*');

<?php

/**
 * INSTRUCTIES — voeg deze regels toe aan bootstrap/app.php
 *
 * In de withRouting() call, voeg toe:
 *
 *     ->withRouting(
 *         web: __DIR__.'/../routes/web.php',
 *         api: __DIR__.'/../routes/api.php',
 *         then: function () {
 *             // Member API
 *             Route::middleware('api')
 *                 ->group(base_path('routes/member-api.php'));
 *
 *             // Member web app
 *             Route::middleware('web')
 *                 ->prefix('member')
 *                 ->group(base_path('routes/member.php'));
 *         },
 *         ...
 *     )
 *
 * Voeg ook toe aan config/auth.php onder 'guards':
 *
 *     'guards' => [
 *         ...
 *         'sanctum' => [
 *             'driver'   => 'sanctum',
 *             'provider' => 'accounts',
 *         ],
 *     ],
 *
 * En onder 'providers':
 *
 *     'providers' => [
 *         ...
 *         'accounts' => [
 *             'driver' => 'eloquent',
 *             'model'  => App\Models\Account::class,
 *         ],
 *     ],
 *
 * Zorg dat Laravel Sanctum geïnstalleerd is:
 *
 *     composer require laravel/sanctum
 *     php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
 *     php artisan migrate
 */

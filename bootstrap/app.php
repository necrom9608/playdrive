<?php

use App\Http\Middleware\RequireBackofficeAuth;
use App\Http\Middleware\RequirePlaydriveCentralHost;
use App\Http\Middleware\RequireFrontdeskAuth;
use App\Http\Middleware\RequirePlaydriveAdminAuth;
use App\Http\Middleware\RequireStaffAuth;
use App\Http\Middleware\RequireValidTenantForApp;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ValidatePublicApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Member API
            Route::middleware('api')
                ->group(base_path('routes/member-api.php'));

            // Member web app
            Route::middleware('web')
                ->prefix('member')
                ->group(base_path('routes/member.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveTenant::class,
            RequireValidTenantForApp::class,
        ]);

        $middleware->api(append: [
            ResolveTenant::class,
            RequireValidTenantForApp::class,
        ]);

        $middleware->alias([
            'frontdesk.auth' => RequireFrontdeskAuth::class,
            'backoffice.auth' => RequireBackofficeAuth::class,
            'staff.auth' => RequireStaffAuth::class,
            'playdrive.admin.auth' => RequirePlaydriveAdminAuth::class,
            'playdrive.admin.host' => RequirePlaydriveCentralHost::class,
            'public.api' => ValidatePublicApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

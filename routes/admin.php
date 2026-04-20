<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\RegionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — bereikbaar op /admin/*
|
| De 'playdrive.admin.host' middleware checkt of het verzoek van een
| toegelaten host komt (zie PLAYDRIVE_ADMIN_ALLOWED_HOSTS in .env).
| Als de env-variabele leeg is, wordt APP_URL als fallback gebruikt.
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware('playdrive.admin.host')
    ->group(function () {

        // ------------------------------------------------------------------
        // API — publiek (geen auth vereist)
        // ------------------------------------------------------------------
        Route::prefix('api')->group(function () {
            Route::post('/auth/login', [AuthController::class, 'login'])->name('admin.api.login');
        });

        // ------------------------------------------------------------------
        // API — beveiligd
        // ------------------------------------------------------------------
        Route::prefix('api')
            ->middleware('playdrive.admin.auth')
            ->group(function () {
                // Auth
                Route::get('/auth/me', [AuthController::class, 'me'])->name('admin.api.me');
                Route::post('/auth/logout', [AuthController::class, 'logout'])->name('admin.api.logout');

                // Tenants
                Route::get('/tenants', [TenantController::class, 'index']);
                Route::post('/tenants', [TenantController::class, 'store']);
                Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
                Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

                // Admin medewerkers
                Route::get('/staff', [StaffController::class, 'index']);
                Route::post('/staff', [StaffController::class, 'store']);
                Route::put('/staff/{id}', [StaffController::class, 'update']);
                Route::delete('/staff/{id}', [StaffController::class, 'destroy']);

                // E-mailtemplates
                Route::get('/email-templates', [EmailTemplateController::class, 'index']);
                Route::put('/email-templates/{key}', [EmailTemplateController::class, 'update']);
                Route::post('/email-templates/{key}/reset', [EmailTemplateController::class, 'reset']);
                Route::post('/email-templates/{key}/preview', [EmailTemplateController::class, 'preview']);

                // Regio's & schoolvakanties
                Route::get('/regions', [RegionController::class, 'index']);
                Route::post('/regions', [RegionController::class, 'store']);
                Route::put('/regions/{region}', [RegionController::class, 'update']);
                Route::delete('/regions/{region}', [RegionController::class, 'destroy']);

                Route::get('/regions/{region}/seasons', [RegionController::class, 'seasons']);
                Route::post('/regions/{region}/seasons', [RegionController::class, 'storeSeason']);
                Route::put('/regions/{region}/seasons/{season}', [RegionController::class, 'updateSeason']);
                Route::delete('/regions/{region}/seasons/{season}', [RegionController::class, 'destroySeason']);
                Route::post('/regions/{region}/seasons/copy', [RegionController::class, 'copySeasons']);
            });

        // ------------------------------------------------------------------
        // Vue SPA catch-all — moet NA de API-routes staan zodat /admin/api/*
        // niet door de SPA wordt afgehandeld
        // ------------------------------------------------------------------
        Route::get('/{any?}', fn () => view('admin.app'))
            ->where('any', '.*')
            ->name('admin.app');
    });

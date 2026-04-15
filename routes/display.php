<?php

use App\Http\Controllers\Display\TenantLogoController;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Route;

Route::get('/tenant-logo', TenantLogoController::class)->name('display.tenant-logo');
Route::get('/manifest.webmanifest', function (CurrentTenant $currentTenant) {
    $tenantName = trim((string) ($currentTenant->tenant?->name ?? ''));
    $appName = $tenantName !== ''
        ? sprintf('Playdrive %s Display', $tenantName)
        : 'Playdrive Display';

    return response()->json([
        'name' => $appName,
        'short_name' => 'Display',
        'description' => 'Realtime customer display for Playdrive',
        'start_url' => '/display',
        'scope' => '/display',
        'display' => 'fullscreen',
        'orientation' => 'portrait',
        'background_color' => '#020617',
        'theme_color' => '#020617',
        'icons' => [
            [
                'src' => '/images/logos/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => '/images/logos/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('display.manifest');
Route::view('/{any?}', 'display.app')->where('any', '.*');

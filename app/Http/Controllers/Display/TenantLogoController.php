<?php

namespace App\Http\Controllers\Display;

use App\Support\CurrentTenant;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class TenantLogoController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant)
    {
        abort_unless($currentTenant->exists(), 404);

        $tenant = $currentTenant->tenant;
        $path = $tenant?->logo_path;

        abort_unless($path, 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $contents = Storage::disk('public')->get($path);
        $lastModified = Storage::disk('public')->lastModified($path);

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Display;

use App\Http\Controllers\Controller;
use App\Support\CurrentTenant;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DisplayTenantAssetController extends Controller
{
    public function logo(CurrentTenant $currentTenant)
    {
        abort_unless($currentTenant->exists(), 404);

        $tenant = $currentTenant->tenant;
        abort_unless($tenant?->logo_path, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($tenant->logo_path), 404);

        $absolutePath = $disk->path($tenant->logo_path);
        $mimeType = $disk->mimeType($tenant->logo_path) ?: 'application/octet-stream';
        $lastModified = $disk->lastModified($tenant->logo_path);

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=300',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }
}

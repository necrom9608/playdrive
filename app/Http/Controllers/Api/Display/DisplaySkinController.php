<?php

namespace App\Http\Controllers\Api\Display;

use App\Domain\Display\DisplaySkin;
use App\Http\Controllers\Controller;
use App\Models\DisplayVideo;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Klantenscherm: welke skin, teksten, filmpjes en openingsuren van vandaag.
 * De tenant komt uit de frontdesk-sessie (ResolveTenant), net als de rest van het scherm.
 *
 *   GET /api/display/skin
 *   GET /display/media/videos/{id}         (met Range-ondersteuning, lang cachebaar via ?v=)
 */
class DisplaySkinController extends Controller
{
    public function show(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen tenant: log in op de frontdesk.');

        return response()->json(DisplaySkin::payload($currentTenant->tenant))
            ->header('Cache-Control', 'no-store');
    }

    public function video(CurrentTenant $currentTenant, int $video): BinaryFileResponse
    {
        abort_unless($currentTenant->exists(), 404);

        $model = DisplayVideo::query()->where('tenant_id', $currentTenant->id())->findOrFail($video);
        abort_unless($model->isPlayable(), 404);

        return response()->file(Storage::disk('local')->path($model->path), [
            'Content-Type'  => 'video/mp4',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Website;

use App\Domain\Games\GameArtwork;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Setup;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Banner van een game of speelplek uitleveren (GET /media/banners/{game|setup}/{id}).
 * Openbaar: het is spel-artwork of een foto van de zaal. De URL draagt ?v=<hash>,
 * dus de browser mag hem lang bewaren.
 */
class BannerMediaController extends Controller
{
    public function show(string $kind, int $id): Response
    {
        $target = $kind === 'setup' ? Setup::query()->find($id) : Game::query()->find($id);
        $path = $target?->banner_path;

        abort_unless($path && Storage::disk(GameArtwork::DISK)->exists($path), 404);

        return Storage::disk(GameArtwork::DISK)->response($path, null, [
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Arcade;

use App\Domain\Arcade\ArcadeIngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/arcade/v1/sync — batch van playdrive-sync (Asphyxia). */
class ArcadeSyncController extends Controller
{
    public function __invoke(Request $request, ArcadeIngestService $ingest): JsonResponse
    {
        $request->validate([
            'players' => ['sometimes', 'array', 'max:2000'],
            'songs'   => ['sometimes', 'array', 'max:5000'],
            'scores'  => ['sometimes', 'array', 'max:2000'],
            'plays'   => ['sometimes', 'array', 'max:2000'],
        ]);

        $result = $ingest->ingest($request->attributes->get('arcade_source'), $request->all());

        return response()->json(['ok' => true, 'result' => $result]);
    }
}

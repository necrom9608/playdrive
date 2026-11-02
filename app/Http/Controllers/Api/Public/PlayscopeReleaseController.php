<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Setups\PlayscopeRelease;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Playscope installeren en bijwerken (publiek: de AppImage bevat geen geheimen;
 * koppelen met een venue gebeurt daarna met een code in de backoffice).
 *
 *   GET /api/playscope/latest.json          actieve versie, checksum, grootte
 *   GET /api/playscope/install.sh           installatiescript (curl … | bash)
 *   GET /api/playscope/download/{versie}    de AppImage
 */
class PlayscopeReleaseController extends Controller
{
    public function latest(): JsonResponse
    {
        $current = PlayscopeRelease::current();
        abort_unless($current, 404, 'Nog geen Playscope-release.');

        return response()->json(PlayscopeRelease::publicArray($current))
            ->header('Cache-Control', 'no-store');
    }

    public function install(): Response
    {
        $file = PlayscopeRelease::dir() . '/install.sh';
        abort_unless(is_file($file), 404, 'Nog geen Playscope-release.');

        return response((string) file_get_contents($file), 200, [
            'Content-Type'  => 'text/x-shellscript; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function download(string $version): BinaryFileResponse
    {
        $file = PlayscopeRelease::file($version);
        abort_unless($file, 404, 'Deze Playscope-versie bestaat niet (meer).');

        return response()->download($file, "Playscope-{$version}.AppImage", [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}

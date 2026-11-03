<?php

namespace App\Http\Controllers\Api\Public;

use App\Domain\Setups\PlayscopeRelease;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Playscope installeren en bijwerken (publiek: de builds bevatten geen geheimen;
 * koppelen met een venue gebeurt daarna met een code in de backoffice).
 *
 *   GET /api/playscope/latest.json[?platform=windows]   actieve versie, checksum, grootte
 *   GET /api/playscope/install.sh                       installatiescript Linux (curl … | bash)
 *   GET /api/playscope/install.ps1                      installatiescript Windows (irm … | iex)
 *   GET /api/playscope/download/{versie}[/{platform}]   de AppImage of de Windows-zip
 */
class PlayscopeReleaseController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $current = PlayscopeRelease::current();
        abort_unless($current, 404, 'Nog geen Playscope-release.');

        $platform = PlayscopeRelease::platform($request->query('platform'));
        $public = PlayscopeRelease::file($current['version'], $platform) ? PlayscopeRelease::publicArray($current, $platform) : null;
        abort_unless($public, 404, "Nog geen Playscope-release voor {$platform}.");

        return response()->json($public)->header('Cache-Control', 'no-store');
    }

    public function install(): Response
    {
        return $this->script('install.sh', 'text/x-shellscript; charset=utf-8');
    }

    public function installWindows(): Response
    {
        return $this->script('install.ps1', 'text/plain; charset=utf-8');
    }

    public function download(string $version, string $platform = 'linux'): BinaryFileResponse
    {
        $file = PlayscopeRelease::file($version, $platform);
        abort_unless($file, 404, 'Deze Playscope-versie bestaat niet (meer).');

        return response()->download($file, PlayscopeRelease::fileName($version, $platform), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function script(string $name, string $type): Response
    {
        $file = PlayscopeRelease::dir() . '/' . $name;
        abort_unless(is_file($file), 404, 'Nog geen Playscope-release.');

        return response((string) file_get_contents($file), 200, [
            'Content-Type'  => $type,
            'Cache-Control' => 'no-store',
        ]);
    }
}

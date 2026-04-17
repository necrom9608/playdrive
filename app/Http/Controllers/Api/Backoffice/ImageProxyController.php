<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImageProxyController extends Controller
{
    /**
     * Allowed host suffixes for security – only proxy our own storage domains.
     */
    private const ALLOWED_HOSTS = [
        'playdrive.be',
    ];

    public function proxy(Request $request): Response
    {
        $url = $request->query('url');

        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Ongeldige URL.');
        }

        $host = parse_url($url, PHP_URL_HOST);

        $allowed = collect(self::ALLOWED_HOSTS)->contains(
            fn ($suffix) => $host === $suffix || Str::endsWith($host, '.' . $suffix)
        );

        if (! $allowed) {
            abort(403, 'Host niet toegestaan voor proxy.');
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get($url);
        } catch (\Throwable $e) {
            abort(502, 'Afbeelding kon niet worden opgehaald.');
        }

        if (! $response->successful()) {
            abort($response->status(), 'Upstream fout bij ophalen afbeelding.');
        }

        $contentType = $response->header('Content-Type') ?? 'image/png';

        // Strip query / hash from content-type if present
        $contentType = explode(';', $contentType)[0];

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Access-Control-Allow-Origin', '*');
    }
}

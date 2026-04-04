<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePublicApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('services.playdrive.public_api_key', '');

        abort_if($configuredKey === '', 500, 'PLAYDRIVE public API key is niet geconfigureerd.');

        $providedKey = trim((string) (
            $request->header('X-Playdrive-Key')
            ?? $request->bearerToken()
            ?? $request->input('api_key')
            ?? ''
        ));

        if ($providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'message' => 'Ongeldige API-sleutel.',
            ], 401);
        }

        return $next($request);
    }
}

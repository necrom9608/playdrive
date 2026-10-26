<?php

namespace App\Http\Middleware;

use App\Models\ArcadeSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticatie voor playdrive-sync (rhythm-server van een venue).
 * Bearer-token → arcade_sources.token_hash (sha256). De tenant volgt uit de bron.
 */
class AuthenticateArcadeSource
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());

        $source = $token === '' ? null : ArcadeSource::query()
            ->where('token_hash', ArcadeSource::hashToken($token))
            ->where('is_active', true)
            ->first();

        if (! $source) {
            return response()->json(['message' => 'Ongeldige of ingetrokken token.'], 401);
        }

        $source->forceFill([
            'last_seen_at' => now(),
            'last_host'    => mb_substr((string) $request->input('source.host', $source->last_host), 0, 255),
        ])->save();

        $request->attributes->set('arcade_source', $source);

        return $next($request);
    }
}

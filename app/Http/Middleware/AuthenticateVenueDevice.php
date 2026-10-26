<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticatie voor onbemande toestellen (kiosk, Playscope).
 * Bearer-token → venue_devices.token_hash (sha256). De tenant volgt uit het toestel,
 * dus er is geen ingelogde gebruiker nodig.
 */
class AuthenticateVenueDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());

        $device = $token === '' ? null : VenueDevice::query()
            ->where('token_hash', VenueDevice::hashSecret($token))
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->first();

        $tenant = $device ? Tenant::query()->find($device->tenant_id) : null;

        if (! $device || ! $tenant?->is_active) {
            return response()->json([
                'message' => 'Toestel niet (meer) gekoppeld.',
                'code'    => 'device_unpaired',
            ], 401);
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip'      => $request->ip(),
        ])->save();

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $request->attributes->set('venue_device', $device);

        return $next($request);
    }
}

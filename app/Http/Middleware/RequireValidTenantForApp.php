<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v1.1 - Tenant-validatie enkel nog van toepassing op display en kiosk apps.
 * Frontdesk, backoffice en staff lossen de tenant op via hun eigen auth-middleware.
 */
class RequireValidTenantForApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);
        $first = $request->segment(1);
        $second = $request->segment(2);

        // Alleen display en kiosk vereisen nog een tenant via de host (subdomein/custom domain).
        // Frontdesk, backoffice en staff lossen de tenant op via hun auth-middleware.
        $tenantWebApps = ['kiosk', 'display'];
        $tenantApiApps = ['display'];

        $requiresTenant = in_array($first, $tenantWebApps, true)
            || ($first === 'api' && in_array($second, $tenantApiApps, true));

        if ($requiresTenant && ! $currentTenant->exists()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Geen geldige tenant gevonden voor deze host.',
                    'host' => $request->getHost(),
                ], 404);
            }

            return response()
                ->view('errors.invalid-tenant', ['host' => $request->getHost()], 404);
        }

        return $next($request);
    }
}

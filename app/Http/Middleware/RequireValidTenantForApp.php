<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireValidTenantForApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);
        $first = $request->segment(1);
        $second = $request->segment(2);

        $tenantWebApps = ['frontdesk', 'backoffice', 'kiosk', 'client', 'staff', 'display'];
        $tenantApiApps = ['frontdesk', 'backoffice', 'display', 'staff'];

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

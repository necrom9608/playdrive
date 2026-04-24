<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v1.2 - Subdomein volledig verwijderd. Tenant wordt opgelost via:
 *   1. De ingelogde frontdesk-sessie (voor display en kiosk routes)
 *   2. Anders: lege CurrentTenant (frontdesk/backoffice/staff vullen die zelf aan)
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $first = $request->segment(1);
        $tenantApps = ['display', 'kiosk'];

        // Voor display en kiosk: probeer tenant te halen uit de frontdesk-sessie.
        // Zo werkt het tweede scherm zonder subdomein.
        if (in_array($first, $tenantApps, true) || $this->isDisplayApiRoute($request)) {
            $auth = $request->session()->get('frontdesk_auth');
            $tenantId = is_array($auth) ? ($auth['tenant_id'] ?? null) : null;

            if ($tenantId) {
                $tenant = Tenant::find((int) $tenantId);

                if ($tenant?->is_active) {
                    app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
                    view()->share('currentTenant', $tenant);

                    return $next($request);
                }
            }
        }

        // Standaard: lege tenant. Auth-middlewares vullen aan na login.
        app()->instance(CurrentTenant::class, new CurrentTenant(null));
        view()->share('currentTenant', null);

        return $next($request);
    }

    private function isDisplayApiRoute(Request $request): bool
    {
        return $request->segment(1) === 'api'
            && $request->segment(2) === 'display';
    }
}

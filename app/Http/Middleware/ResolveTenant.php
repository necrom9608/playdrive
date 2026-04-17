<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v1.1 - Tenant wordt niet langer opgelost via het subdomein.
 * De CurrentTenant wordt initieel leeg ingesteld en later gevuld
 * door de auth-middleware op basis van de ingelogde gebruiker.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Stel een lege CurrentTenant in als standaard.
        // De auth-middlewares (RequireFrontdeskAuth, RequireBackofficeAuth, RequireStaffAuth)
        // vullen deze aan na succesvolle authenticatie.
        app()->instance(CurrentTenant::class, new CurrentTenant(null));
        view()->share('currentTenant', null);

        return $next($request);
    }
}

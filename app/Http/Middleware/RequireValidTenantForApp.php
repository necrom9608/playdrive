<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v1.2 - Subdomein volledig verwijderd.
 * Display en kiosk laden de tenant nu via de frontdesk-sessie (ResolveTenant).
 * Deze middleware laat de request door zolang ResolveTenant de tenant heeft ingesteld.
 * Als er geen sessie is (display nog niet gekoppeld aan frontdesk), wordt de app
 * gewoon geladen — de Vue-app zelf toont dan de gekoppeld/ontkoppeld-state.
 */
class RequireValidTenantForApp
{
    public function handle(Request $request, Closure $next): Response
    {
        // Geen tenant-check meer op basis van host/subdomein.
        // Display en kiosk worden geladen zoals frontdesk — tenant via sessie.
        // Als er geen tenant is, laadt de Vue-app gewoon op en handelt het zelf af.
        return $next($request);
    }
}

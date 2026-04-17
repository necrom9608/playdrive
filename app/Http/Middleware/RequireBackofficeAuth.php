<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v1.1 - Tenant wordt niet langer via subdomein opgelost maar via de ingelogde user.
 * Na succesvolle authenticatie wordt de CurrentTenant ingesteld op basis van user->tenant_id.
 */
class RequireBackofficeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->session()->get('backoffice_auth');

        if (! is_array($auth)) {
            return response()->json(['message' => 'Niet aangemeld voor de backoffice.'], 401);
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId) {
            $request->session()->forget('backoffice_auth');
            return response()->json(['message' => 'Niet aangemeld voor de backoffice.'], 401);
        }

        $user = User::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->where('is_admin', true)
            ->find($userId);

        if (! $user) {
            $request->session()->forget('backoffice_auth');
            return response()->json(['message' => 'Geen geldige backoffice-sessie gevonden.'], 401);
        }

        // Stel CurrentTenant in op basis van de ingelogde user (i.p.v. subdomein).
        $tenant = Tenant::find($user->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $request->attributes->set('backoffice_user', $user);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal auth middleware.
 *
 * Werking is identiek aan RequireBackofficeAuth maar gebruikt een aparte
 * sessie-key 'portal_auth' zodat een gebruiker tegelijk in de Backoffice EN
 * in het Portal kan inloggen zonder conflict.
 *
 * Toegang tot het Portal vereist:
 *   - Een geldige user_id en tenant_id in de session
 *   - User moet actief zijn (is_active)
 *   - Tenant moet bestaan
 *   - Voorlopig: is_admin=true of role in (admin, manager). Komt later in een
 *     fijner rolsysteem; nu houden we het bewust ruim zodat free-tier owners
 *     ook gewoon kunnen inloggen.
 */
class RequirePortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->session()->get('portal_auth');

        if (! is_array($auth)) {
            return response()->json(['message' => 'Niet aangemeld voor het portal.'], 401);
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId) {
            $request->session()->forget('portal_auth');
            return response()->json(['message' => 'Niet aangemeld voor het portal.'], 401);
        }

        $user = User::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->find($userId);

        if (! $user || ! $this->canAccessPortal($user)) {
            $request->session()->forget('portal_auth');
            return response()->json(['message' => 'Geen geldige portal-sessie gevonden.'], 401);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant) {
            $request->session()->forget('portal_auth');
            return response()->json(['message' => 'Tenant niet gevonden.'], 401);
        }

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $request->attributes->set('portal_user', $user);
        $request->attributes->set('portal_tenant', $tenant);

        return $next($request);
    }

    private function canAccessPortal(User $user): bool
    {
        // is_admin blijft werken voor backwards compatibility
        if ($user->is_admin) {
            return true;
        }

        // Nieuwe rol-check
        return in_array($user->role, ['admin', 'manager'], true);
    }
}

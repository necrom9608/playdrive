<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBackofficeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);
        $auth = $request->session()->get('backoffice_auth');

        if (! is_array($auth) || ! $currentTenant->exists()) {
            return response()->json(['message' => 'Niet aangemeld voor de backoffice.'], 401);
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId || (int) $tenantId !== (int) $currentTenant->id()) {
            $request->session()->forget('backoffice_auth');
            return response()->json(['message' => 'Niet aangemeld voor de backoffice.'], 401);
        }

        $user = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->where('is_admin', true)
            ->find($userId);

        if (! $user) {
            $request->session()->forget('backoffice_auth');
            return response()->json(['message' => 'Geen geldige backoffice-sessie gevonden.'], 401);
        }

        $request->attributes->set('backoffice_user', $user);

        return $next($request);
    }
}

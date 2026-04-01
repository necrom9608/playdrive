<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->session()->get('staff_auth');

        if (! is_array($auth)) {
            return response()->json(['message' => 'Niet aangemeld.'], 401);
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId) {
            $request->session()->forget('staff_auth');
            return response()->json(['message' => 'Niet aangemeld.'], 401);
        }

        $user = User::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            $request->session()->forget('staff_auth');
            return response()->json(['message' => 'Sessie verlopen.'], 401);
        }

        $request->attributes->set('staff_user', $user);

        return $next($request);
    }
}

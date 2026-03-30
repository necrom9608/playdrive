<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFrontdeskAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);

        $auth = $request->session()->get('frontdesk_auth');

        if (! is_array($auth)) {
            return response()->json([
                'message' => 'Niet ingelogd.',
            ], 401);
        }

        $userId = $auth['user_id'] ?? null;
        $tenantId = $auth['tenant_id'] ?? null;

        if (! $userId || ! $tenantId || (int) $tenantId !== (int) $currentTenant->id()) {
            $request->session()->forget('frontdesk_auth');

            return response()->json([
                'message' => 'Niet ingelogd.',
            ], 401);
        }

        $user = User::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            $request->session()->forget('frontdesk_auth');

            return response()->json([
                'message' => 'Niet ingelogd.',
            ], 401);
        }

        $request->attributes->set('frontdesk_user', $user);

        return $next($request);
    }
}

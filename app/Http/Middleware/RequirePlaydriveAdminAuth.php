<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlaydriveAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('playdrive_admin_auth', false)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Niet ingelogd.'], 401);
            }

            return redirect()->route('admin.app');
        }

        return $next($request);
    }
}

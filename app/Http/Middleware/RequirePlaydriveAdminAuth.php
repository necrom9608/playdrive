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
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}

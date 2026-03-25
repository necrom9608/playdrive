<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantResolver;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected TenantResolver $tenantResolver
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantResolver->resolveFromHost($request->getHost());

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        view()->share('currentTenant', $tenant);

        return $next($request);
    }
}

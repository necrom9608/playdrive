<?php

use App\Models\Tenant;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        $request = request();

        if ($request && $request->attributes->has('currentTenant')) {
            return $request->attributes->get('currentTenant');
        }

        return app()->bound('currentTenant')
            ? app('currentTenant')
            : null;
    }
}

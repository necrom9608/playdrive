<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;

class TenantResolver
{
    public function resolveFromHost(?string $host): ?Tenant
    {
        if (! $host) {
            return null;
        }

        $domain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', $host)
            ->first();

        if (! $domain || ! $domain->tenant || ! $domain->tenant->is_active) {
            return null;
        }

        return $domain->tenant;
    }
}

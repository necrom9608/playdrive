<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;

class TenantResolver
{
    public function resolveFromHost(?string $host): ?Tenant
    {
        if (! $host) {
            return null;
        }

        return Tenant::query()
            ->where('primary_domain', $host)
            ->where('is_active', true)
            ->first();
    }
}

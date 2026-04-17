<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;

/**
 * v1.1 - TenantResolver wordt enkel nog gebruikt voor display en kiosk apps
 * die via een subdomein of custom domain werken.
 * Frontdesk, backoffice en staff lossen de tenant op via de ingelogde user.
 */
class TenantResolver
{
    public function resolveFromHost(?string $host): ?Tenant
    {
        if (! $host) {
            return null;
        }

        $normalizedHost = strtolower($host);

        $domain = TenantDomain::query()
            ->with('tenant')
            ->whereRaw('lower(domain) = ?', [$normalizedHost])
            ->first();

        if ($domain?->tenant?->is_active) {
            return $domain->tenant;
        }

        $slug = $this->extractTenantSlug($normalizedHost);

        if (! $slug) {
            return null;
        }

        return Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    private function extractTenantSlug(string $host): ?string
    {
        $segments = explode('.', $host);

        if (count($segments) < 3) {
            return null;
        }

        $slug = $segments[0] ?? null;

        if (! $slug) {
            return null;
        }

        if (in_array($slug, ['frontdesk', 'backoffice', 'client', 'kiosk', 'www'], true)) {
            return null;
        }

        return $slug;
    }
}

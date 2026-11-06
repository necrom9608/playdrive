<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Vaste link waarmee klanten lid worden van een venue: https://playdrive.be/join/{slug}.
 * Deze link staat in de QR-code op het klantenscherm en op affiches: nooit wijzigen.
 */
class JoinLink
{
    public static function slug(Tenant $tenant): ?string
    {
        $slug = trim((string) ($tenant->public_slug ?: $tenant->slug));

        return $slug === '' ? null : $slug;
    }

    public static function url(Tenant $tenant): ?string
    {
        $slug = self::slug($tenant);

        return $slug ? rtrim((string) config('app.url'), '/').'/join/'.rawurlencode($slug) : null;
    }

    /** Venue op basis van de slug in de link (public_slug eerst, dan de interne slug). */
    public static function tenantFor(string $slug): ?Tenant
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return Tenant::query()->where('is_active', true)->where('public_slug', $slug)->first()
            ?? Tenant::query()->where('is_active', true)->where('slug', $slug)->first();
    }
}

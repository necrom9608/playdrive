<?php

namespace App\Domain\Setups;

/**
 * Playscope-releases die PlayDrive uitdeelt aan de Playscope-pc's.
 *
 * Gebouwd en gepubliceerd door deploy/release-playscope.sh in de Playscope-repo
 * (Playmix Ops → Playscope → Uitrollen), in storage/app/playscope:
 *   releases/<versie>/Playscope.AppImage + manifest.json
 *   current.json   manifest van de actieve release (rollback = andere release actief)
 *   install.sh     installatiescript met het adres van deze PlayDrive
 *
 * De pc's krijgen de actieve versie mee met de heartbeat (`update`) zodra die verschilt
 * van wat ze draaien, en werken zichzelf bij (electron/updater.js in Playscope).
 */
final class PlayscopeRelease
{
    public static function dir(): string
    {
        return storage_path('app/playscope');
    }

    /** Manifest van de actieve release, of null. */
    public static function current(): ?array
    {
        $file = self::dir() . '/current.json';
        if (! is_file($file)) {
            return null;
        }
        $m = json_decode((string) file_get_contents($file), true);

        return is_array($m) && isset($m['version'], $m['sha256']) && self::isVersion($m['version']) ? $m : null;
    }

    public static function isVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    /** Pad naar de AppImage van een bewaarde release, of null. */
    public static function file(string $version): ?string
    {
        if (! self::isVersion($version)) {
            return null;
        }
        $file = self::dir() . "/releases/{$version}/Playscope.AppImage";

        return is_file($file) ? $file : null;
    }

    public static function downloadUrl(string $version): string
    {
        return url("/api/playscope/download/{$version}");
    }

    /** Wat de pc te zien krijgt: de actieve release, enkel als die verschilt van wat hij draait. */
    public static function updateFor(?string $installed): ?array
    {
        $current = self::current();
        if (! $current || $installed === $current['version'] || ! self::file($current['version'])) {
            return null;
        }

        return self::publicArray($current);
    }

    public static function publicArray(array $m): array
    {
        return [
            'version'  => $m['version'],
            'url'      => self::downloadUrl($m['version']),
            'sha256'   => $m['sha256'],
            'size'     => (int) ($m['size'] ?? 0),
            'built_at' => $m['built_at'] ?? null,
        ];
    }
}

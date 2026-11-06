<?php

namespace App\Domain\Setups;

/**
 * Playscope-releases die PlayDrive uitdeelt aan de Playscope-pc's.
 *
 * Gebouwd en gepubliceerd door deploy/release-playscope.sh in de Playscope-repo
 * (Playmix Ops → Playscope → Uitrollen), in storage/app/playscope:
 *   releases/<versie>/Playscope.AppImage     Linux (Bazzite)
 *   releases/<versie>/Playscope-win.zip      Windows (racers met TeknoParrot), uitgepakt = Playscope.exe + co.
 *   releases/<versie>/manifest.json          versie, commit, sha256/size (Linux, oud formaat) + files.{linux,windows}
 *   current.json   manifest van de actieve release (rollback = andere release actief)
 *   install.sh     installatiescript (Linux) met het adres van deze PlayDrive
 *   install.ps1    idem voor Windows
 *
 * De pc's krijgen de actieve versie voor hun platform mee met de heartbeat (`update`) zodra
 * die verschilt van wat ze draaien, en werken zichzelf bij (electron/updater.js in Playscope).
 *
 * Downloaden: de bestanden staan ook (hard link) in storage/app/public/playscope/<versie>/;
 * /api/playscope/download/... stuurt dan door naar /storage/playscope/... zodat nginx ze
 * rechtstreeks geeft (snel, hervatten mogelijk, geen PHP-worker per download). Zonder die
 * kopie of zonder de public/storage-link gaat het zoals vroeger via PHP.
 */
final class PlayscopeRelease
{
    public const PLATFORMS = ['linux', 'windows'];

    private const FILES = [
        'linux'   => 'Playscope.AppImage',
        'windows' => 'Playscope-win.zip',
    ];

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

    public static function platform(?string $platform): string
    {
        return in_array($platform, self::PLATFORMS, true) ? $platform : 'linux';
    }

    /** Pad naar het bestand van een bewaarde release voor dat platform, of null. */
    public static function file(string $version, string $platform = 'linux'): ?string
    {
        if (! self::isVersion($version)) {
            return null;
        }
        $file = self::dir() . "/releases/{$version}/" . self::FILES[self::platform($platform)];

        return is_file($file) ? $file : null;
    }

    /** Adres van de statische kopie (nginx) van dat bestand, of null als die er niet is. */
    public static function staticUrl(string $version, string $platform = 'linux'): ?string
    {
        if (! self::isVersion($version)) {
            return null;
        }
        $rel = "playscope/{$version}/" . self::FILES[self::platform($platform)];
        // Via public/storage (de link van storage:link): zo is zeker dat nginx hem ook vindt.
        if (! is_file(public_path("storage/{$rel}"))) {
            return null;
        }

        return url("/storage/{$rel}");
    }

    public static function fileName(string $version, string $platform = 'linux'): string
    {
        return self::platform($platform) === 'windows' ? "Playscope-{$version}-win.zip" : "Playscope-{$version}.AppImage";
    }

    public static function downloadUrl(string $version, string $platform = 'linux'): string
    {
        // Linux zonder platform in de url: zo blijven oudere Playscope-versies werken.
        return self::platform($platform) === 'windows'
            ? url("/api/playscope/download/{$version}/windows")
            : url("/api/playscope/download/{$version}");
    }

    /** sha256/size van het bestand voor dat platform uit het manifest, of null (niet gebouwd). */
    public static function fileInfo(array $m, string $platform): ?array
    {
        $platform = self::platform($platform);
        $f = $m['files'][$platform] ?? null;
        if (is_array($f) && isset($f['sha256'])) {
            return ['sha256' => $f['sha256'], 'size' => (int) ($f['size'] ?? 0)];
        }

        return $platform === 'linux' ? ['sha256' => $m['sha256'], 'size' => (int) ($m['size'] ?? 0)] : null;
    }

    /** Wat de pc te zien krijgt: de actieve release, enkel als die verschilt van wat hij draait. */
    public static function updateFor(?string $installed, ?string $platform = 'linux'): ?array
    {
        $platform = self::platform($platform);
        $current = self::current();
        if (! $current || $installed === $current['version'] || ! self::file($current['version'], $platform)) {
            return null;
        }

        return self::publicArray($current, $platform);
    }

    public static function publicArray(array $m, string $platform = 'linux'): ?array
    {
        $info = self::fileInfo($m, $platform);
        if (! $info) {
            return null;
        }

        return [
            'version'  => $m['version'],
            'platform' => self::platform($platform),
            'url'      => self::downloadUrl($m['version'], $platform),
            'sha256'   => $info['sha256'],
            'size'     => $info['size'],
            'built_at' => $m['built_at'] ?? null,
        ];
    }
}

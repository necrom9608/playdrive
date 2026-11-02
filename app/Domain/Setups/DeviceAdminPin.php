<?php

namespace App\Domain\Setups;

/**
 * Beheerpincode voor onbemande toestellen (Playscope): verlaat de kioskmodus op de PC.
 *
 * Eén pincode per venue (tenants.device_admin_pin). PlayDrive bewaart enkel een hash en
 * stuurt die mee met de heartbeat; het toestel controleert de code zelf, zodat dat ook
 * werkt als PlayDrive even niet bereikbaar is.
 *
 * Formaat: pbkdf2_sha256$<iteraties>$<salt base64>$<hash base64>
 * (PBKDF2-HMAC-SHA256, 32 bytes; in Node: crypto.pbkdf2Sync(pin, salt, iter, 32, 'sha256')).
 */
final class DeviceAdminPin
{
    public const MIN_LENGTH = 4;
    public const MAX_LENGTH = 8;
    public const ITERATIONS = 120000;

    public static function isValid(string $pin): bool
    {
        return (bool) preg_match('/^\d{' . self::MIN_LENGTH . ',' . self::MAX_LENGTH . '}$/', $pin);
    }

    public static function hash(string $pin): string
    {
        $salt = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $pin, $salt, self::ITERATIONS, 32, true);

        return sprintf('pbkdf2_sha256$%d$%s$%s', self::ITERATIONS, base64_encode($salt), base64_encode($hash));
    }

    public static function verify(string $pin, ?string $stored): bool
    {
        if (! $stored) {
            return false;
        }
        $parts = explode('$', $stored);
        if (count($parts) !== 4 || $parts[0] !== 'pbkdf2_sha256') {
            return false;
        }
        [, $iterations, $salt, $hash] = $parts;
        $calc = hash_pbkdf2('sha256', $pin, base64_decode($salt), (int) $iterations, 32, true);

        return hash_equals(base64_decode($hash), $calc);
    }
}

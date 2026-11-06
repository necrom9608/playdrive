<?php

namespace App\Domain\Setups;

use App\Models\VenueDevice;

/**
 * Toestel koppelen met de code die het toestel (kiosk, Playscope) zelf toont.
 *
 * Gebruikt door de backoffice (code intikken) en de staff-app (QR-code op het scherm scannen;
 * de QR bevat <PlayDrive>/staff/koppel/<code>).
 */
class DevicePairing
{
    public const NOT_FOUND = 'Code niet gevonden of verlopen. Controleer de code op het scherm van het toestel.';

    /** Toestel dat nog wacht op koppeling met deze code (niet geclaimd, niet verlopen), of null. */
    public static function pending(string $code): ?VenueDevice
    {
        $code = VenueDevice::normalizeCode($code);
        if ($code === '') {
            return null;
        }

        return VenueDevice::query()
            ->whereNull('tenant_id')
            ->where('pairing_code', $code)
            ->where('pairing_expires_at', '>', now())
            ->first();
    }

    /**
     * Toestel aan de venue koppelen. Null = code niet gevonden of verlopen.
     * Atomisch, zodat twee mensen niet tegelijk dezelfde code pakken (409).
     */
    public static function claim(int $tenantId, string $code, string $name, ?int $setupId): ?VenueDevice
    {
        $device = self::pending($code);
        if (! $device) {
            return null;
        }

        $claimed = VenueDevice::query()
            ->whereKey($device->id)
            ->whereNull('tenant_id')
            ->update([
                'tenant_id'  => $tenantId,
                'name'       => $name,
                'setup_id'   => $setupId,
                'claimed_at' => now(),
                'is_active'  => true,
            ]);

        abort_unless($claimed === 1, 409, 'Dit toestel werd net door iemand anders gekoppeld.');

        return $device->fresh('setup');
    }
}

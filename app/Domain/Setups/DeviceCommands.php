<?php

namespace App\Domain\Setups;

use App\Models\VenueDevice;
use App\Models\VenueDeviceCommand;

/**
 * Bediening op afstand van een Playscope-pc vanuit de backoffice.
 *
 *   backoffice  POST /api/backoffice/venue-devices/{id}/commands  → wachtrij (status pending)
 *   toestel     GET  /api/device/v1/commands   (elke 5 s)          → sent
 *   toestel     POST /api/device/v1/commands/{id}  {status, result} → done | failed
 *
 * Een opdracht die niet binnen EXPIRE_SECONDS opgehaald wordt (toestel offline), vervalt:
 * een pc die een uur later weer online komt, mag niet alsnog herstarten.
 */
final class DeviceCommands
{
    public const EXPIRE_SECONDS = 120;

    public const COMMANDS = [
        'stop_game'   => 'Game stoppen',
        'restart_app' => 'Playscope herstarten',
        'update_now'  => 'Update nu installeren',
        'message'     => 'Bericht tonen',
        'kiosk_off'   => 'Kioskmodus uit',
        'kiosk_on'    => 'Kioskmodus aan',
        'reboot'      => 'Pc herstarten',
        'shutdown'    => 'Pc uitzetten',
    ];

    public static function label(string $command): string
    {
        return self::COMMANDS[$command] ?? $command;
    }

    /** Openstaande opdrachten voor het toestel; oude worden eerst op "expired" gezet. */
    public static function takePending(VenueDevice $device): array
    {
        VenueDeviceCommand::query()
            ->where('venue_device_id', $device->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subSeconds(self::EXPIRE_SECONDS))
            ->update(['status' => 'expired', 'result' => 'Toestel was niet bereikbaar.', 'updated_at' => now()]);

        $commands = VenueDeviceCommand::query()
            ->where('venue_device_id', $device->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($commands->isNotEmpty()) {
            VenueDeviceCommand::query()
                ->whereIn('id', $commands->pluck('id'))
                ->where('status', 'pending')
                ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
        }

        return $commands->map(fn (VenueDeviceCommand $c) => $c->toDeviceArray())->values()->all();
    }
}

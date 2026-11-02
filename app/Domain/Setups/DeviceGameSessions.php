<?php

namespace App\Domain\Setups;

use App\Models\Game;
use App\Models\VenueDevice;
use App\Models\VenueDeviceGameSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Speelbeurten van Playscope opslaan (POST /api/device/v1/sessions).
 *
 * Playscope houdt per gestarte game één beurt bij (electron/stats.js) en stuurt ze door
 * zodra PlayDrive bereikbaar is. Elke beurt heeft een uuid: wat al bestaat wordt genegeerd.
 *
 * Antwoord `stored` = alle id's die het toestel uit zijn wachtrij mag halen: opgeslagen,
 * al bekend, én ongeldig (anders blijft een kapotte beurt eeuwig terugkomen).
 */
class DeviceGameSessions
{
    public const MAX_BATCH = 100;

    /** Langer dan dit is geen echte beurt (klok verkeerd, pc dagen blijven staan). */
    private const MAX_SECONDS = 7 * 24 * 3600;

    public const END_REASONS = [
        'exit'          => 'Game zelf afgesloten',
        'user'          => 'Afgesloten in Playscope',
        'remote'        => 'Gestopt vanuit PlayDrive',
        'idle'          => 'Inactief',
        'replaced'      => 'Andere game gestart',
        'start_failed'  => 'Start mislukt',
        'start_timeout' => 'Start duurde te lang',
        'interrupted'   => 'Playscope of pc ging uit',
    ];

    /**
     * @param  array<int, mixed>  $sessions
     * @return array{stored: string[], rejected: int}
     */
    public static function store(VenueDevice $device, array $sessions): array
    {
        $tz = config('app.timezone');
        $rows = [];
        $ids = [];
        $rejected = 0;

        foreach (array_slice($sessions, 0, self::MAX_BATCH) as $s) {
            $id = is_array($s) && is_string($s['id'] ?? null) ? $s['id'] : null;
            $v = Validator::make(is_array($s) ? $s : [], [
                'id'         => ['required', 'uuid'],
                'app_id'     => ['required', 'string', 'max:64'],
                'name'       => ['nullable', 'string', 'max:255'],
                'started_at' => ['required', 'date'],
                'running_at' => ['nullable', 'date'],
                'ended_at'   => ['required', 'date'],
                'duration_s' => ['required', 'integer', 'min:0', 'max:' . self::MAX_SECONDS],
                'active_s'   => ['required', 'integer', 'min:0', 'max:' . self::MAX_SECONDS],
                'end_reason' => ['required', 'string', 'in:' . implode(',', array_keys(self::END_REASONS))],
            ]);

            if ($v->fails()) {
                $rejected++;
                if ($id !== null) {
                    $ids[] = $id;
                }
                continue;
            }

            try {
                $parse = fn (?string $t) => $t === null ? null : Carbon::parse($t)->setTimezone($tz)->format('Y-m-d H:i:s');
                $now = now()->format('Y-m-d H:i:s');
                $rows[$s['id']] = [
                    'uuid'             => $s['id'],
                    'tenant_id'        => $device->tenant_id,
                    'venue_device_id'  => $device->id,
                    'device_name'      => $device->name,
                    'game_id'          => null,
                    'source'           => 'steam',
                    'external_id'      => $s['app_id'],
                    'game_name'        => $s['name'] ?? null,
                    'started_at'       => $parse($s['started_at']),
                    'running_at'       => $parse($s['running_at'] ?? null),
                    'ended_at'         => $parse($s['ended_at']),
                    'duration_seconds' => (int) $s['duration_s'],
                    'active_seconds'   => min((int) $s['active_s'], (int) $s['duration_s']),
                    'end_reason'       => $s['end_reason'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            } catch (Throwable) {
                $rejected++;
            }
            $ids[] = $s['id'];
        }

        if ($rows) {
            // Koppelen aan de game in de catalogus van de venue (bestaat normaal: Playscope meldde hem).
            $games = Game::query()
                ->where('tenant_id', $device->tenant_id)
                ->where('source', 'steam')
                ->whereIn('external_id', array_unique(array_column($rows, 'external_id')))
                ->pluck('id', 'external_id');

            foreach ($rows as &$row) {
                $row['game_id'] = $games[$row['external_id']] ?? null;
            }
            unset($row);

            VenueDeviceGameSession::query()->insertOrIgnore(array_values($rows));
        }

        return ['stored' => array_values(array_unique($ids)), 'rejected' => $rejected];
    }
}

<?php

namespace App\Domain\Setups;

use App\Models\DeviceGroup;
use App\Models\Game;
use App\Models\VenueDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Automatische rotatie van een groep Playscope-toestellen (bv. de racers met TeknoParrot).
 *
 * De server beslist; de toestellen volgen. Elke Playscope-pc in een groep vraagt elke paar
 * seconden POST /api/device/v1/rotation en meldt daarbij hoe lang er al geen input was
 * (idle_s). Het antwoord zegt welke game er nu moet draaien (+ epoch).
 *
 * Wisselen (advance):
 *   - nog geen game, of de game staat niet meer in de afspeellijst → eerste game
 *   - wisseltijd (rotation_minutes) voorbij → wachten tot op ALLE toestellen van de groep
 *     die zich recent meldden minstens quiet_seconds niemand iets deed (einde van een race),
 *     ten laatste grace_minutes na de wisseltijd
 *   - gepauzeerd → huidige game blijft
 *   - "Nu wisselen" in de backoffice → meteen (switchTo)
 * Bij elke wissel gaat epoch omhoog en begint de tijd opnieuw te lopen.
 *
 * Alles met lockForUpdate op de groep: alle pc's vragen tegelijk, maar er wordt maar één keer gewisseld.
 */
final class DeviceRotation
{
    /** Een melding ouder dan dit telt niet (toestel offline of Playscope hangt). */
    public const REPORT_FRESH_SECONDS = 20;

    /** Playscope vraagt zo vaak (seconden); gaat mee in het antwoord. */
    public const POLL_SECONDS = 5;

    /**
     * Melding van een toestel verwerken en zeggen wat er moet draaien.
     *
     * @param  array{epoch?: int|null, game?: string|null, phase?: string|null, idle_s?: int|null}  $report
     */
    public static function poll(VenueDevice $device, array $report): array
    {
        $device->forceFill([
            'rotation_report' => [
                'epoch'  => isset($report['epoch']) ? (int) $report['epoch'] : null,
                'game'   => $report['game'] ?? null,
                'phase'  => $report['phase'] ?? null,
                'idle_s' => isset($report['idle_s']) ? max(0, (int) $report['idle_s']) : null,
                'error'  => $report['error'] ?? null,
            ],
            'rotation_reported_at' => now(),
        ])->save();

        if (! $device->device_group_id) {
            return ['group' => null, 'poll_s' => self::POLL_SECONDS];
        }

        $group = DB::transaction(function () use ($device) {
            $group = DeviceGroup::query()->lockForUpdate()->find($device->device_group_id);
            if ($group) {
                self::advance($group);
            }

            return $group;
        });

        return $group ? self::payload($group) : ['group' => null, 'poll_s' => self::POLL_SECONDS];
    }

    /** Wisselen als het moet (zie bovenaan). Verwacht een vergrendelde groep (in een transactie). */
    public static function advance(DeviceGroup $group): void
    {
        $playlist = $group->playlistIds();

        if (! $playlist) {
            if ($group->current_game_id !== null) {
                $group->forceFill(['current_game_id' => null, 'current_started_at' => null, 'epoch' => $group->epoch + 1])->save();
            }

            return;
        }

        if (! $group->current_game_id || ! in_array((int) $group->current_game_id, $playlist, true)) {
            self::start($group, $playlist[0]);

            return;
        }

        if ($group->is_paused || ! $group->current_started_at) {
            return;
        }

        $due = self::dueAt($group);
        if (now()->lt($due)) {
            return;
        }

        if (now()->gte($due->copy()->addMinutes($group->grace_minutes)) || self::quiet($group)) {
            self::start($group, self::nextAfter($playlist, (int) $group->current_game_id));
        }
    }

    /** "Nu wisselen": naar de gevraagde game (moet in de afspeellijst staan) of de volgende. */
    public static function switchTo(DeviceGroup $group, ?int $gameId = null): void
    {
        DB::transaction(function () use ($group, $gameId) {
            $g = DeviceGroup::query()->lockForUpdate()->findOrFail($group->id);
            $playlist = $g->playlistIds();
            if (! $playlist) {
                return;
            }
            $target = $gameId !== null && in_array($gameId, $playlist, true)
                ? $gameId
                : self::nextAfter($playlist, (int) $g->current_game_id);
            self::start($g, $target);
        });
    }

    /** Pauzeren (huidige game blijft) of hervatten (de wisseltijd begint opnieuw). */
    public static function setPaused(DeviceGroup $group, bool $paused): void
    {
        DB::transaction(function () use ($group, $paused) {
            $g = DeviceGroup::query()->lockForUpdate()->findOrFail($group->id);
            $fill = ['is_paused' => $paused];
            if (! $paused && $g->is_paused && $g->current_game_id) {
                $fill['current_started_at'] = now();
            }
            $g->forceFill($fill)->save();
        });
    }

    private static function start(DeviceGroup $group, int $gameId): void
    {
        $group->forceFill([
            'current_game_id'    => $gameId,
            'current_started_at' => now(),
            'epoch'              => $group->epoch + 1,
        ])->save();
    }

    /** @param  int[]  $playlist */
    private static function nextAfter(array $playlist, int $current): int
    {
        $i = array_search($current, $playlist, true);

        return $playlist[$i === false ? 0 : ($i + 1) % count($playlist)];
    }

    public static function dueAt(DeviceGroup $group): Carbon
    {
        return ($group->current_started_at ?? now())->copy()->addMinutes($group->rotation_minutes);
    }

    /** Speelt er niemand meer? Enkel toestellen die zich recent meldden tellen mee. */
    public static function quiet(DeviceGroup $group): bool
    {
        $fresh = now()->subSeconds(self::REPORT_FRESH_SECONDS);

        $devices = VenueDevice::query()
            ->where('device_group_id', $group->id)
            ->where('is_active', true)
            ->where('rotation_reported_at', '>=', $fresh)
            ->get(['id', 'rotation_report', 'rotation_reported_at']);

        foreach ($devices as $d) {
            if (self::idleSeconds($d) < $group->quiet_seconds) {
                return false;
            }
        }

        return true;
    }

    /** Seconden zonder input op dit toestel, nu (melding + tijd sinds de melding). Null = onbekend. */
    public static function idleSeconds(VenueDevice $d): ?int
    {
        $idle = $d->rotation_report['idle_s'] ?? null;
        if ($idle === null || ! $d->rotation_reported_at) {
            return null;
        }

        return (int) $idle + (int) max(0, $d->rotation_reported_at->diffInSeconds(now(), false));
    }

    /** Wat een toestel (en de backoffice) te zien krijgt. */
    public static function payload(DeviceGroup $group): array
    {
        $group->loadMissing('currentGame');
        $playlist = $group->games()->get(['games.id', 'games.name', 'games.source', 'games.external_id']);
        $current = $group->currentGame;

        $state = 'playing';
        if (! $playlist->count()) {
            $state = 'empty';
        } elseif ($group->is_paused) {
            $state = 'paused';
        } elseif ($group->current_started_at && now()->gte(self::dueAt($group))) {
            $state = 'waiting';
        }

        $next = null;
        if ($current && $playlist->count() > 1) {
            $nextId = self::nextAfter($playlist->pluck('id')->map(fn ($id) => (int) $id)->all(), (int) $current->id);
            $next = $playlist->firstWhere('id', $nextId);
        }

        $due = $group->current_started_at ? self::dueAt($group) : null;

        return [
            'group'       => ['id' => $group->id, 'name' => $group->name],
            'state'       => $state,
            'paused'      => (bool) $group->is_paused,
            'epoch'       => (int) $group->epoch,
            'game'        => $current ? self::gameArray($current) : null,
            'next'        => $next ? self::gameArray($next) : null,
            'started_at'  => $group->current_started_at?->toIso8601String(),
            'due_at'      => $due?->toIso8601String(),
            'switch_by'   => $due?->copy()->addMinutes($group->grace_minutes)->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'poll_s'      => self::POLL_SECONDS,
        ];
    }

    private static function gameArray(Game $g): array
    {
        return ['game_id' => $g->id, 'source' => $g->source, 'id' => $g->external_id, 'name' => $g->name];
    }
}

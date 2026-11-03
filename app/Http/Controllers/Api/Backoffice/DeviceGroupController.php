<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Setups\DeviceRotation;
use App\Http\Controllers\Controller;
use App\Models\DeviceGroup;
use App\Models\Game;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Groepen van Playscope-toestellen met automatische rotatie (Speelplekken → Groepen).
 *
 *   GET    /device-groups              groepen + status, Playscope-toestellen (met hun games), games
 *   POST   /device-groups              nieuwe groep
 *   PUT    /device-groups/{id}         naam, timing, toestellen, afspeellijst
 *   DELETE /device-groups/{id}         toestellen worden weer gewone Playscope-pc's
 *   POST   /device-groups/{id}/next    nu wisselen { game_id? }
 *   POST   /device-groups/{id}/pause   { paused }
 */
class DeviceGroupController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        // Zonder dat er een toestel vraagt, toch al wisselen als het moet (bv. alle pc's uit).
        $groups = DeviceGroup::query()->where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->get();

        $devices = VenueDevice::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'playscope')
            ->with('games:games.id')
            ->orderBy('name')
            ->get();

        $gameIds = $devices->flatMap(fn (VenueDevice $d) => $d->games->pluck('id'))->unique();
        $games = Game::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereIn('id', $gameIds)->orWhereIn('id', DB::table('device_group_games')
                ->whereIn('device_group_id', $groups->pluck('id'))->select('game_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'source', 'external_id']);

        return response()->json([
            'groups'  => $groups->map(fn (DeviceGroup $g) => $this->groupArray($g, $devices))->values(),
            'devices' => $devices->map(fn (VenueDevice $d) => [
                'id'              => $d->id,
                'name'            => $d->name,
                'online'          => $d->isOnline(),
                'platform'        => $d->platform,
                'device_group_id' => $d->device_group_id,
                'game_ids'        => $d->games->pluck('id')->values(),
            ])->values(),
            'games' => $games->map(fn (Game $g) => [
                'id'     => $g->id,
                'name'   => $g->name,
                'source' => $g->source,
                'source_label' => Game::SOURCES[$g->source] ?? $g->source,
            ])->values(),
            'limits' => [
                'rotation_minutes' => [DeviceGroup::ROTATION_MINUTES_MIN, DeviceGroup::ROTATION_MINUTES_MAX],
                'quiet_seconds'    => [DeviceGroup::QUIET_SECONDS_MIN, DeviceGroup::QUIET_SECONDS_MAX],
                'grace_minutes'    => [0, DeviceGroup::GRACE_MINUTES_MAX],
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $data = $this->validated($request, $currentTenant);

        $group = DB::transaction(function () use ($data, $currentTenant) {
            $group = DeviceGroup::query()->create([
                'tenant_id'        => $currentTenant->id(),
                'name'             => $data['name'],
                'rotation_minutes' => $data['rotation_minutes'],
                'quiet_seconds'    => $data['quiet_seconds'],
                'grace_minutes'    => $data['grace_minutes'],
                'sort_order'       => (int) DeviceGroup::query()->where('tenant_id', $currentTenant->id())->max('sort_order') + 1,
            ]);
            $this->sync($group, $data);

            return $group;
        });

        return response()->json(['id' => $group->id], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $deviceGroup);
        $data = $this->validated($request, $currentTenant);

        DB::transaction(function () use ($deviceGroup, $data) {
            $deviceGroup->fill([
                'name'             => $data['name'],
                'rotation_minutes' => $data['rotation_minutes'],
                'quiet_seconds'    => $data['quiet_seconds'],
                'grace_minutes'    => $data['grace_minutes'],
            ])->save();
            $this->sync($deviceGroup, $data);
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $deviceGroup);

        VenueDevice::query()->where('device_group_id', $deviceGroup->id)->update(['device_group_id' => null]);
        $deviceGroup->delete();

        return response()->json(['ok' => true]);
    }

    public function next(Request $request, CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $deviceGroup);
        $data = $request->validate(['game_id' => ['nullable', 'integer']]);

        DeviceRotation::switchTo($deviceGroup, isset($data['game_id']) ? (int) $data['game_id'] : null);

        return response()->json(DeviceRotation::payload($deviceGroup->fresh()));
    }

    public function pause(Request $request, CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $deviceGroup);
        $data = $request->validate(['paused' => ['required', 'boolean']]);

        DeviceRotation::setPaused($deviceGroup, (bool) $data['paused']);

        return response()->json(DeviceRotation::payload($deviceGroup->fresh()));
    }

    // ---------------------------------------------------------------- hulpjes

    private function validated(Request $request, CurrentTenant $currentTenant): array
    {
        $tenantId = $currentTenant->id();

        return $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'rotation_minutes' => ['required', 'integer', 'min:' . DeviceGroup::ROTATION_MINUTES_MIN, 'max:' . DeviceGroup::ROTATION_MINUTES_MAX],
            'quiet_seconds'    => ['required', 'integer', 'min:' . DeviceGroup::QUIET_SECONDS_MIN, 'max:' . DeviceGroup::QUIET_SECONDS_MAX],
            'grace_minutes'    => ['required', 'integer', 'min:0', 'max:' . DeviceGroup::GRACE_MINUTES_MAX],
            'device_ids'       => ['present', 'array'],
            'device_ids.*'     => ['integer', Rule::exists('venue_devices', 'id')->where('tenant_id', $tenantId)->where('type', 'playscope')],
            'game_ids'         => ['present', 'array', 'max:200'],
            'game_ids.*'       => ['integer', 'distinct', Rule::exists('games', 'id')->where('tenant_id', $tenantId)],
        ], [
            'rotation_minutes.min' => 'Minstens ' . DeviceGroup::ROTATION_MINUTES_MIN . ' minuten per game.',
        ]);
    }

    /** Toestellen en afspeellijst (volgorde zoals doorgegeven). Een toestel kan maar in één groep. */
    private function sync(DeviceGroup $group, array $data): void
    {
        VenueDevice::query()
            ->where('device_group_id', $group->id)
            ->whereNotIn('id', $data['device_ids'])
            ->update(['device_group_id' => null]);
        if ($data['device_ids']) {
            VenueDevice::query()->whereIn('id', $data['device_ids'])->update(['device_group_id' => $group->id]);
        }

        $sync = [];
        foreach (array_values($data['game_ids']) as $i => $id) {
            $sync[(int) $id] = ['sort_order' => $i];
        }
        $group->games()->sync($sync);
    }

    private function groupArray(DeviceGroup $group, $devices): array
    {
        DB::transaction(function () use ($group) {
            $locked = DeviceGroup::query()->lockForUpdate()->find($group->id);
            if ($locked) {
                DeviceRotation::advance($locked);
                $group->setRawAttributes($locked->getAttributes(), true);
            }
        });

        $members = $devices->where('device_group_id', $group->id)->values();
        $fresh = now()->subSeconds(DeviceRotation::REPORT_FRESH_SECONDS);

        return [
            'id'               => $group->id,
            'name'             => $group->name,
            'rotation_minutes' => $group->rotation_minutes,
            'quiet_seconds'    => $group->quiet_seconds,
            'grace_minutes'    => $group->grace_minutes,
            'is_paused'        => (bool) $group->is_paused,
            'device_ids'       => $members->pluck('id')->values(),
            'game_ids'         => $group->playlistIds(),
            'status'           => DeviceRotation::payload($group),
            'members'          => $members->map(fn (VenueDevice $d) => [
                'id'      => $d->id,
                'name'    => $d->name,
                'online'  => $d->isOnline(),
                'state'   => $d->state,
                'report'  => $d->rotation_reported_at && $d->rotation_reported_at->gte($fresh) ? $d->rotation_report : null,
                'idle_s'  => $d->rotation_reported_at && $d->rotation_reported_at->gte($fresh) ? DeviceRotation::idleSeconds($d) : null,
            ])->values(),
        ];
    }

    private function authorizeTenant(CurrentTenant $currentTenant, DeviceGroup $group): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $group->tenant_id === (int) $currentTenant->id(), 404);
    }
}

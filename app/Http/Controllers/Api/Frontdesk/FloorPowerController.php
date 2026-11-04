<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PowerHub;
use App\Models\PowerPlug;
use App\Models\PowerWave;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Slimme stekkers (IKEA DIRIGERA) vanuit de kassa. De hubs zijn enkel lokaal bereikbaar: de
 * kassa-app (Tauri) praat er rechtstreeks mee en geeft PlayDrive door wat ze ziet.
 *
 *   GET  /floor/power                    hubs (met token als gekoppeld), golven, stekkers
 *   POST /floor/power/hubs/{id}/token    {token}  na koppelen (knop op de hub)
 *   POST /floor/power/hubs/{id}/sync     {devices: [...]} of {error}
 */
class FloorPowerController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $hubs = PowerHub::query()->where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'hubs'  => $hubs->map(fn (PowerHub $h) => [
                ...$h->toPublicArray(),
                // Enkel de kassa heeft dit nodig om de hub in het lokale netwerk aan te spreken.
                'token' => $h->isPaired() ? $h->token : null,
            ])->values(),
            'waves' => PowerWave::query()->where('tenant_id', $tenantId)->orderBy('position')->orderBy('id')->get()
                ->map(fn (PowerWave $w) => $w->toPublicArray())->values(),
            'plugs' => PowerPlug::query()->where('tenant_id', $tenantId)->whereNull('missing_since')
                ->orderBy('wave_order')->orderBy('name')->get()
                ->map(fn (PowerPlug $p) => $p->toPublicArray())->values(),
        ]);
    }

    public function storeToken(Request $request, CurrentTenant $currentTenant, PowerHub $powerHub): JsonResponse
    {
        $this->authorizeHub($currentTenant, $powerHub);
        $data = $request->validate(['token' => ['required', 'string', 'max:4000']]);

        $powerHub->forceFill([
            'token'        => $data['token'],
            'paired_at'    => now(),
            'last_seen_at' => now(),
            'last_error'   => null,
        ])->save();

        return response()->json($powerHub->toPublicArray());
    }

    public function sync(Request $request, CurrentTenant $currentTenant, PowerHub $powerHub): JsonResponse
    {
        $this->authorizeHub($currentTenant, $powerHub);

        $data = $request->validate([
            'error'               => ['nullable', 'string', 'max:255'],
            'devices'             => ['nullable', 'array', 'max:500'],
            'devices.*.id'        => ['required', 'string', 'max:100'],
            'devices.*.name'      => ['nullable', 'string', 'max:255'],
            'devices.*.room'      => ['nullable', 'string', 'max:100'],
            'devices.*.model'     => ['nullable', 'string', 'max:100'],
            'devices.*.is_on'     => ['nullable', 'boolean'],
            'devices.*.reachable' => ['nullable', 'boolean'],
            'devices.*.power_w'   => ['nullable', 'numeric'],
        ]);

        if (filled($data['error'] ?? null)) {
            $powerHub->forceFill(['last_error' => mb_substr($data['error'], 0, 255)])->save();

            return response()->json(['ok' => true]);
        }

        if (! array_key_exists('devices', $data) || $data['devices'] === null) {
            return response()->json(['ok' => true]);
        }

        $now = now();
        $created = 0;

        DB::transaction(function () use ($powerHub, $data, $now, &$created) {
            $existing = PowerPlug::query()->where('power_hub_id', $powerHub->id)->get()->keyBy('external_id');
            $seen = [];

            foreach ($data['devices'] as $item) {
                $externalId = (string) $item['id'];
                $seen[] = $externalId;
                $power = isset($item['power_w']) ? round((float) $item['power_w'], 1) : null;
                $name = trim((string) ($item['name'] ?? '')) ?: (trim((string) ($item['model'] ?? '')) ?: 'Stekker');

                $values = [
                    'name'          => $name,
                    'room'          => $item['room'] ?? null,
                    'model'         => $item['model'] ?? null,
                    'is_on'         => $item['is_on'] ?? null,
                    'is_reachable'  => $item['reachable'] ?? null,
                    'power_w'       => $power,
                    'missing_since' => null,
                    'synced_at'     => $now,
                ];

                $plug = $existing->get($externalId);
                if ($plug) {
                    $plug->forceFill([...$values, 'has_metering' => $plug->has_metering || $power !== null])->save();
                } else {
                    PowerPlug::query()->create([
                        ...$values,
                        'tenant_id'    => $powerHub->tenant_id,
                        'power_hub_id' => $powerHub->id,
                        'external_id'  => $externalId,
                        'has_metering' => $power !== null,
                    ]);
                    $created++;
                }
            }

            // Niet meer op de hub: bijhouden (koppeling blijft), in de backoffice te verwijderen.
            PowerPlug::query()
                ->where('power_hub_id', $powerHub->id)
                ->whereNotIn('external_id', $seen ?: [''])
                ->whereNull('missing_since')
                ->update(['missing_since' => $now, 'updated_at' => $now]);

            $powerHub->forceFill(['last_seen_at' => $now, 'last_error' => null])->save();
        });

        return response()->json(['ok' => true, 'new' => $created]);
    }

    private function authorizeHub(CurrentTenant $currentTenant, PowerHub $hub): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $hub->tenant_id === (int) $currentTenant->id(), 404);
    }
}

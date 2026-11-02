<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Setups\PlayscopeRelease;
use App\Domain\Setups\SetupTypes;
use App\Http\Controllers\Controller;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API voor gekoppelde toestellen (Bearer-token, middleware venue.device).
 *
 *   GET  /api/device/v1/me         → toestel, venue en gekoppelde speelplek
 *   POST /api/device/v1/heartbeat  → levensteken + huidige toestand (Playscope: welk spel)
 *
 * Beide geven ook `admin_pin` mee: de hash van de beheerpincode van de venue (of null).
 * Playscope bewaart die en controleert de code zelf (zie App\Domain\Setups\DeviceAdminPin).
 * De heartbeat van een Playscope-pc geeft ook `update`: de actieve Playscope-release als die
 * verschilt van de versie op de pc (zie App\Domain\Setups\PlayscopeRelease), anders null.
 * En `settings`: instellingen van dit toestel ({ idle_minutes }: game afsluiten na zoveel
 * minuten zonder input, 0 = uit).
 */
class DeviceController extends Controller
{
    public function me(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');
        $device->load('setup');

        return response()->json($this->payload($device, $currentTenant));
    }

    public function heartbeat(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        $data = $request->validate([
            'app_version'  => ['nullable', 'string', 'max:40'],
            'platform'     => ['nullable', 'string', 'max:40'],
            'hostname'     => ['nullable', 'string', 'max:100'],
            'state'        => ['nullable', 'array'],
            'state.status' => ['nullable', 'string', 'in:idle,playing,attract,admin'],
            'state.game'   => ['nullable', 'string', 'max:255'],
            'state.since'  => ['nullable', 'date'],
        ]);

        $fill = array_filter([
            'app_version' => $data['app_version'] ?? null,
            'platform'    => $data['platform'] ?? null,
            'hostname'    => $data['hostname'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('state', $data)) {
            $fill['state'] = $data['state'] ? array_intersect_key($data['state'], array_flip(['status', 'game', 'since'])) : null;
            $fill['state_updated_at'] = now();
        }

        if ($fill) {
            $device->forceFill($fill)->save();
        }

        $device->load('setup');

        return response()->json([
            'ok'          => true,
            'server_time' => now()->toIso8601String(),
            'setup'       => $device->setup ? $this->setupPayload($device->setup) : null,
            'admin_pin'   => $currentTenant->tenant?->device_admin_pin,
            'update'      => $device->type === 'playscope' ? PlayscopeRelease::updateFor($device->app_version) : null,
            'settings'    => $device->deviceSettings(),
        ]);
    }

    private function payload(VenueDevice $device, CurrentTenant $currentTenant): array
    {
        $tenant = $currentTenant->tenant;

        return [
            'device' => $device->toPublicArray(),
            'tenant' => [
                'id'           => $tenant?->id,
                'display_name' => $tenant?->display_name,
                'logo_url'     => $tenant?->logo_url,
            ],
            'setup'  => $device->setup ? $this->setupPayload($device->setup) : null,
            'admin_pin' => $tenant?->device_admin_pin,
        ];
    }

    private function setupPayload(Setup $setup): array
    {
        return [
            'id'          => $setup->id,
            'name'        => $setup->name,
            'type'        => $setup->type,
            'type_label'  => SetupTypes::label($setup->type),
            'zone'        => $setup->zone,
            'games'       => $setup->games ?? [],
            'max_players' => $setup->max_players,
            'status'      => $setup->status,
            'qr_code'     => $setup->qr_code,
            'qr_url'      => $setup->qrUrl(),
        ];
    }
}

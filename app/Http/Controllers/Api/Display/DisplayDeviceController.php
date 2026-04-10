<?php

namespace App\Http\Controllers\Api\Display;

use App\Http\Controllers\Controller;
use App\Models\DisplayDevice;
use App\Models\PosDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DisplayDeviceController extends Controller
{
    public function bootstrap(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $payload = $request->validate([
            'role' => ['required', 'in:display,pos'],
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'pairing_uuid' => ['nullable', 'uuid'],
        ]);

        return $payload['role'] === 'display'
            ? $this->bootstrapDisplay($payload, $currentTenant)
            : $this->bootstrapPos($payload, $currentTenant);
    }

    public function state(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $payload = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
        ]);

        $query = DisplayDevice::query()
            ->where('device_uuid', $payload['device_uuid'])
            ->where('device_token', $payload['device_token']);

        if ($currentTenant->exists()) {
            $query->where('tenant_id', $currentTenant->id());
        }

        $device = $query->firstOrFail();

        $device->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'data' => $this->transformDisplayDevice($device),
        ]);
    }

    protected function bootstrapDisplay(array $payload, CurrentTenant $currentTenant): JsonResponse
    {
        $query = DisplayDevice::query()->where('device_uuid', $payload['device_uuid']);

        if ($currentTenant->exists()) {
            $query->where('tenant_id', $currentTenant->id());
        }

        $device = $query->first();

        if (! $device) {
            $device = new DisplayDevice();
            $device->tenant_id = $currentTenant->exists() ? $currentTenant->id() : null;
            $device->device_uuid = $payload['device_uuid'];
            $device->device_token = Str::random(64);
            $device->pairing_uuid = (string) Str::uuid();
            $device->current_mode = DisplayDevice::MODE_STANDBY;
            $device->current_payload = [];
            $device->is_active = true;
        } elseif (($payload['device_token'] ?? null) !== $device->device_token) {
            return response()->json([
                'message' => 'Deze display kon niet geverifieerd worden. Koppel het toestel opnieuw.',
            ], 403);
        }

        $device->name = $payload['name'] ?: $device->name ?: 'Customer Display';
        $device->last_seen_at = now();
        $device->save();

        return response()->json([
            'data' => $this->transformDisplayDevice($device),
        ]);
    }

    protected function bootstrapPos(array $payload, CurrentTenant $currentTenant): JsonResponse
    {
        $query = PosDevice::query()->where('device_uuid', $payload['device_uuid']);

        if ($currentTenant->exists()) {
            $query->where('tenant_id', $currentTenant->id());
        }

        $device = $query->first();

        if (! $device) {
            $device = new PosDevice();
            $device->tenant_id = $currentTenant->exists() ? $currentTenant->id() : null;
            $device->device_uuid = $payload['device_uuid'];
            $device->device_token = Str::random(64);
            $device->is_active = true;
        } elseif (($payload['device_token'] ?? null) !== $device->device_token) {
            return response()->json([
                'message' => 'Deze POS-terminal kon niet geverifieerd worden. Koppel het toestel opnieuw.',
            ], 403);
        }

        $device->name = $payload['name'] ?: $device->name ?: 'POS Terminal';
        $device->last_seen_at = now();

        if (! empty($payload['pairing_uuid'])) {
            $displayQuery = DisplayDevice::query()->where('pairing_uuid', $payload['pairing_uuid']);

            if ($currentTenant->exists()) {
                $displayQuery->where('tenant_id', $currentTenant->id());
            }

            $display = $displayQuery->first();

            if ($display) {
                $device->display_device_id = $display->id;
            }
        }

        $device->save();
        $device->load('displayDevice');

        return response()->json([
            'data' => $this->transformPosDevice($device),
        ]);
    }

    protected function transformDisplayDevice(DisplayDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_uuid' => $device->device_uuid,
            'device_token' => $device->device_token,
            'pairing_uuid' => $device->pairing_uuid,
            'current_mode' => $device->current_mode,
            'current_registration_id' => $device->current_registration_id,
            'current_payload' => $device->current_payload ?? [],
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
            'last_synced_at' => optional($device->last_synced_at)?->toIso8601String(),
            'is_active' => (bool) $device->is_active,
            'paired_pos_count' => $device->posDevices()->count(),
            'is_paired' => $device->posDevices()->exists(),
        ];
    }

    protected function transformPosDevice(PosDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_uuid' => $device->device_uuid,
            'device_token' => $device->device_token,
            'display_device_id' => $device->display_device_id,
            'display_pairing_uuid' => $device->displayDevice?->pairing_uuid,
            'display_name' => $device->displayDevice?->name,
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
            'is_active' => (bool) $device->is_active,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Display;

use App\Events\DisplayStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\DisplayDevice;
use App\Models\PosDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function bootstrap(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $data = $request->validate([
            'role' => ['required', 'in:display,pos'],
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['nullable', 'string'],
            'pairing_uuid' => ['nullable', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['role'] === 'display') {
            $device = DisplayDevice::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('device_uuid', $data['device_uuid'])
                ->first();

            if (! $device) {
                $device = DisplayDevice::create([
                    'tenant_id' => $currentTenant->id(),
                    'name' => $data['name'] ?: 'Customer Display',
                    'device_uuid' => $data['device_uuid'],
                    'device_token' => Str::random(64),
                    'pairing_uuid' => (string) Str::uuid(),
                    'last_seen_at' => now(),
                    'current_mode' => DisplayDevice::MODE_STANDBY,
                    'current_payload' => [],
                    'is_active' => true,
                ]);
            } else {
                if ($device->device_token && $data['device_token'] && $device->device_token !== $data['device_token']) {
                    abort(403, 'Ongeldig display token.');
                }

                if (! $device->device_token) {
                    $device->device_token = Str::random(64);
                }

                if (! $device->pairing_uuid) {
                    $device->pairing_uuid = (string) Str::uuid();
                }

                $device->fill([
                    'name' => $data['name'] ?: $device->name,
                    'last_seen_at' => now(),
                    'is_active' => true,
                ])->save();
            }

            return response()->json([
                'data' => $this->transformDisplayDevice($device),
            ]);
        }

        $device = PosDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        if (! $device) {
            $device = PosDevice::create([
                'tenant_id' => $currentTenant->id(),
                'name' => $data['name'] ?: 'Frontdesk POS',
                'device_uuid' => $data['device_uuid'],
                'device_token' => Str::random(64),
                'last_seen_at' => now(),
                'is_active' => true,
            ]);
        } else {
            if ($device->device_token && $data['device_token'] && $device->device_token !== $data['device_token']) {
                abort(403, 'Ongeldig POS token.');
            }

            if (! $device->device_token) {
                $device->device_token = Str::random(64);
            }

            $device->fill([
                'name' => $data['name'] ?: $device->name,
                'last_seen_at' => now(),
                'is_active' => true,
            ])->save();
        }

        if (! empty($data['pairing_uuid'])) {
            $display = DisplayDevice::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('pairing_uuid', $data['pairing_uuid'])
                ->first();

            abort_unless($display, 404, 'Display met deze koppelcode niet gevonden.');

            $device->display_device_id = $display->id;
            $device->save();
        }

        $device->load('displayDevice');

        return response()->json([
            'data' => $this->transformPosDevice($device),
        ]);
    }

    public function state(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
        ]);

        $device = DisplayDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        abort_unless($device, 404, 'Display niet gevonden.');

        if (! hash_equals((string) $device->device_token, (string) $data['device_token'])) {
            abort(403, 'Ongeldig display token.');
        }

        $device->update([
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'data' => $this->transformDisplayDevice($device),
        ]);
    }

    public function sync(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $data = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
            'mode' => ['required', 'in:standby,reservation,member_registration'],
            'reservation_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $pos = PosDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('device_uuid', $data['device_uuid'])
            ->first();

        abort_unless($pos, 404, 'POS device niet gevonden.');

        if (! hash_equals((string) $pos->device_token, (string) $data['device_token'])) {
            abort(403, 'Ongeldig POS token.');
        }

        abort_unless($pos->display_device_id, 422, 'Deze POS is nog niet gekoppeld aan een display.');

        $display = DisplayDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->find($pos->display_device_id);

        abort_unless($display, 404, 'Gekoppelde display niet gevonden.');

        $normalizedPayload = $this->normalizePayload($data['payload'] ?? [], $data['reservation_id'] ?? null);

        $display->update([
            'current_mode' => $data['mode'],
            'current_payload' => $normalizedPayload,
            'last_seen_at' => now(),
        ]);

        $pos->update([
            'last_seen_at' => now(),
        ]);

        broadcast(new DisplayStateUpdated($display, $normalizedPayload));

        return response()->json([
            'data' => [
                'display_device_id' => $display->id,
                'current_mode' => $display->current_mode,
                'broadcast_channel' => 'display.' . $display->id,
                'current_payload' => $normalizedPayload,
            ],
        ]);
    }

    protected function normalizePayload(array $payload, ?int $reservationId = null): array
    {
        $reservation = Arr::get($payload, 'reservation')
            ?? Arr::get($payload, 'registration');

        $order = Arr::get($payload, 'order');

        return [
            'reservation' => $reservation,
            'order' => $order,
            'reservation_id' => $reservationId
                ?? Arr::get($payload, 'reservation_id')
                ?? Arr::get($payload, 'registration_id')
                ?? Arr::get($reservation, 'id'),
            'synced_at' => now()->toIso8601String(),
        ];
    }

    protected function transformDisplayDevice(DisplayDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_uuid' => $device->device_uuid,
            'device_token' => $device->device_token,
            'pairing_uuid' => $device->pairing_uuid,
            'broadcast_channel' => 'display.' . $device->id,
            'current_mode' => $device->current_mode,
            'current_payload' => $device->current_payload ?? [],
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
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
            'display_name' => $device->displayDevice?->name,
            'display_pairing_uuid' => $device->displayDevice?->pairing_uuid,
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
            'is_active' => (bool) $device->is_active,
        ];
    }
}

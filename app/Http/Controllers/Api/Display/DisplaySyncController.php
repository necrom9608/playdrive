<?php

namespace App\Http\Controllers\Api\Display;

use App\Http\Controllers\Controller;
use App\Models\DisplayDevice;
use App\Models\PosDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisplaySyncController extends Controller
{
    public function sync(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $payload = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'device_token' => ['required', 'string'],
            'mode' => ['required', 'in:standby,reservation'],
            'reservation_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $posQuery = PosDevice::query()
            ->with('displayDevice')
            ->where('device_uuid', $payload['device_uuid'])
            ->where('device_token', $payload['device_token']);

        if ($currentTenant->exists()) {
            $posQuery->where('tenant_id', $currentTenant->id());
        }

        /** @var PosDevice $posDevice */
        $posDevice = $posQuery->firstOrFail();
        $displayDevice = $posDevice->displayDevice;

        if (! $displayDevice instanceof DisplayDevice) {
            return response()->json([
                'message' => 'Er is nog geen display gekoppeld aan deze POS-terminal.',
            ], 422);
        }

        $displayDevice->forceFill([
            'current_mode' => $payload['mode'],
            'current_registration_id' => $payload['reservation_id'] ?? null,
            'current_payload' => $payload['payload'] ?? [],
            'last_synced_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Display gesynchroniseerd.',
            'data' => [
                'display_device_id' => $displayDevice->id,
                'display_name' => $displayDevice->name,
                'mode' => $displayDevice->current_mode,
            ],
        ]);
    }
}

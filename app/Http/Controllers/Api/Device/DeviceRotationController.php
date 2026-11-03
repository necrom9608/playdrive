<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Setups\DeviceRotation;
use App\Http\Controllers\Controller;
use App\Models\VenueDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rotatie voor Playscope-toestellen in een groep (Bearer-token, middleware venue.device).
 *
 *   POST /api/device/v1/rotation
 *     { epoch, game: "teknoparrot:WMMT5"|null, phase: idle|starting|running, idle_s, error? }
 *   → { group, state, paused, epoch, game: {source, id, name}, next, started_at, due_at, switch_by, poll_s }
 *     group = null: dit toestel zit (niet meer) in een groep → gewone Playscope.
 *
 * Zie App\Domain\Setups\DeviceRotation.
 */
class DeviceRotationController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        $data = $request->validate([
            'epoch'  => ['nullable', 'integer', 'min:0'],
            'game'   => ['nullable', 'string', 'max:100'],
            'phase'  => ['nullable', 'string', 'in:idle,starting,running,switching'],
            'idle_s' => ['nullable', 'integer', 'min:0'],
            'error'  => ['nullable', 'string', 'max:200'],
        ]);

        return response()->json(DeviceRotation::poll($device, $data));
    }
}

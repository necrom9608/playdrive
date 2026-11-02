<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Setups\DeviceGameSessions;
use App\Http\Controllers\Controller;
use App\Models\VenueDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Speelbeurten van dit toestel (Bearer-token, middleware venue.device).
 *
 *   POST /api/device/v1/sessions  { sessions: [ { id, app_id, name, started_at, running_at,
 *                                    ended_at, duration_s, active_s, end_reason }, … ] }
 *                                 → { stored: [id, …], rejected: n }
 */
class DeviceSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        $data = $request->validate([
            'sessions' => ['present', 'array', 'max:' . DeviceGameSessions::MAX_BATCH],
        ]);

        return response()->json(['ok' => true, ...DeviceGameSessions::store($device, $data['sessions'])]);
    }
}

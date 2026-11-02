<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Setups\DeviceCommands;
use App\Http\Controllers\Controller;
use App\Models\VenueDevice;
use App\Models\VenueDeviceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opdrachten voor dit toestel (Bearer-token, middleware venue.device).
 *
 *   GET  /api/device/v1/commands        openstaande opdrachten (worden "sent")
 *   POST /api/device/v1/commands/{id}   {status: done|failed, result?}
 */
class DeviceCommandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        return response()->json(['commands' => DeviceCommands::takePending($device)]);
    }

    public function ack(Request $request, int $command): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        $data = $request->validate([
            'status' => ['required', 'string', 'in:done,failed'],
            'result' => ['nullable', 'string', 'max:500'],
        ]);

        $row = VenueDeviceCommand::query()
            ->where('venue_device_id', $device->id)
            ->whereKey($command)
            ->firstOrFail();

        $row->forceFill([
            'status'  => $data['status'],
            'result'  => $data['result'] ?? null,
            'done_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }
}

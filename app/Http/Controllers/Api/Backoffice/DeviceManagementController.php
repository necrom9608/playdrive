<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\DisplayDevice;
use App\Models\PosDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceManagementController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $displays = DisplayDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->withCount('posDevices')
            ->with(['posDevices:id,name,display_device_id'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $posDevices = PosDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->with('displayDevice')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'displays' => $displays,
            'pos_devices' => $posDevices,
        ]);
    }

    public function pair(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden voor deze host.');

        $data = $request->validate([
            'pos_device_id' => ['required', 'integer'],
            'display_device_id' => ['required', 'integer'],
        ]);

        $pos = PosDevice::query()->where('tenant_id', $currentTenant->id())->findOrFail($data['pos_device_id']);
        $display = DisplayDevice::query()->where('tenant_id', $currentTenant->id())->findOrFail($data['display_device_id']);

        PosDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('display_device_id', $display->id)
            ->where('id', '!=', $pos->id)
            ->update(['display_device_id' => null]);

        $pos->update([
            'display_device_id' => $display->id,
        ]);

        return response()->json([
            'message' => 'POS en display gekoppeld.',
        ]);
    }

    public function unpair(PosDevice $posDevice, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $posDevice->tenant_id === (int) $currentTenant->id(), 404);

        $posDevice->update([
            'display_device_id' => null,
        ]);

        return response()->json([
            'message' => 'Koppeling verwijderd.',
        ]);
    }

    public function updatePos(Request $request, PosDevice $posDevice, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $posDevice->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $posDevice->update($data);

        return response()->json([
            'data' => $posDevice->fresh('displayDevice'),
        ]);
    }

    public function updateDisplay(Request $request, DisplayDevice $displayDevice, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $displayDevice->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $displayDevice->update($data);

        return response()->json([
            'data' => $displayDevice->fresh(),
        ]);
    }

    public function destroyPos(PosDevice $posDevice, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $posDevice->tenant_id === (int) $currentTenant->id(), 404);

        $posDevice->delete();

        return response()->json([
            'message' => 'POS-terminal verwijderd.',
        ]);
    }

    public function destroyDisplay(DisplayDevice $displayDevice, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $displayDevice->tenant_id === (int) $currentTenant->id(), 404);

        PosDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('display_device_id', $displayDevice->id)
            ->update(['display_device_id' => null]);

        $displayDevice->delete();

        return response()->json([
            'message' => 'Display verwijderd.',
        ]);
    }
}


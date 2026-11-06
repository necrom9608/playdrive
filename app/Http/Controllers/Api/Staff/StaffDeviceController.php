<?php

namespace App\Http\Controllers\Api\Staff;

use App\Domain\Setups\DevicePairing;
use App\Http\Controllers\Controller;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Toestellen koppelen vanuit de staff-app: QR-code op het scherm van Playscope of de kiosk
 * scannen (of de code intikken), naam en speelplek kiezen. Enkel admins, net als in de backoffice.
 */
class StaffDeviceController extends Controller
{
    /** Speelplekken om uit te kiezen (zonder gearchiveerde). */
    public function setups(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $setups = Setup::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('status', '!=', Setup::STATUS_ARCHIVED)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'zone']);

        return response()->json(['setups' => $setups]);
    }

    /** Wat er achter een koppelcode zit (om te tonen voor het koppelen). */
    public function lookup(Request $request, string $code): JsonResponse
    {
        $this->authorizeAdmin($request);

        $device = DevicePairing::pending($code);
        if (! $device) {
            return response()->json(['message' => DevicePairing::NOT_FOUND], 404);
        }

        return response()->json([
            'code'        => $device->pairing_code,
            'type'        => $device->type,
            'type_label'  => VenueDevice::typeLabel($device->type),
            'hostname'    => $device->hostname,
            'platform'    => $device->platform,
            'app_version' => $device->app_version,
            'expires_at'  => $device->pairing_expires_at?->toIso8601String(),
        ]);
    }

    public function claim(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'max:20'],
            'name'         => ['required', 'string', 'max:255'],
            'setup_id'     => [
                'nullable', 'integer',
                Rule::exists((new Setup())->getTable(), 'id')->where('tenant_id', $currentTenant->id()),
            ],
        ]);

        $device = DevicePairing::claim($currentTenant->id(), $data['pairing_code'], $data['name'], $data['setup_id'] ?? null);
        if (! $device) {
            return response()->json(['message' => DevicePairing::NOT_FOUND], 422);
        }

        return response()->json($device->toPublicArray(), 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->attributes->get('staff_user');
        abort_unless($user && (bool) $user->is_admin, 403, 'Enkel admins kunnen toestellen koppelen.');
    }
}

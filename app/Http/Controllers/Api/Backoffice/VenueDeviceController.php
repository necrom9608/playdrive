<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van onbemande toestellen (kioskschermen, Playscope-PC's).
 * Koppelen gebeurt door de code in te geven die het toestel zelf toont.
 */
class VenueDeviceController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $devices = VenueDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->with('setup:id,name')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (VenueDevice $d) => $d->toPublicArray())
            ->values();

        $types = collect(VenueDevice::TYPES)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values();

        return response()->json([
            'devices' => $devices,
            'types'   => $types,
        ]);
    }

    public function claim(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'max:20'],
            'name'         => ['required', 'string', 'max:255'],
            'setup_id'     => ['nullable', 'integer', $this->setupRule($currentTenant)],
        ]);

        $code = VenueDevice::normalizeCode($data['pairing_code']);

        $device = VenueDevice::query()
            ->whereNull('tenant_id')
            ->where('pairing_code', $code)
            ->where('pairing_expires_at', '>', now())
            ->first();

        if (! $device) {
            return response()->json([
                'message' => 'Code niet gevonden of verlopen. Controleer de code op het scherm van het toestel.',
            ], 422);
        }

        // Atomisch claimen zodat twee admins niet tegelijk dezelfde code pakken.
        $claimed = VenueDevice::query()
            ->whereKey($device->id)
            ->whereNull('tenant_id')
            ->update([
                'tenant_id'  => $currentTenant->id(),
                'name'       => $data['name'],
                'setup_id'   => $data['setup_id'] ?? null,
                'claimed_at' => now(),
                'is_active'  => true,
            ]);

        abort_unless($claimed === 1, 409, 'Dit toestel werd net door iemand anders gekoppeld.');

        return response()->json($device->fresh('setup')->toPublicArray(), 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $venueDevice);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'setup_id'  => ['nullable', 'integer', $this->setupRule($currentTenant)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $venueDevice->fill([
            'name'      => $data['name'],
            'setup_id'  => $data['setup_id'] ?? null,
            'is_active' => $data['is_active'] ?? $venueDevice->is_active,
        ])->save();

        return response()->json($venueDevice->fresh('setup')->toPublicArray());
    }

    /** Verwijderen = token ongeldig. Het toestel toont daarna opnieuw een koppelcode. */
    public function destroy(CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $venueDevice);

        $venueDevice->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeTenant(CurrentTenant $currentTenant, VenueDevice $device): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $device->tenant_id === (int) $currentTenant->id(), 404);
    }

    private function setupRule(CurrentTenant $currentTenant)
    {
        return Rule::exists((new Setup())->getTable(), 'id')->where('tenant_id', $currentTenant->id());
    }
}

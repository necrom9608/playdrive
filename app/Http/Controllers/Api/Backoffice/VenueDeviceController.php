<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Games\GameCatalog;
use App\Domain\Setups\DeviceAdminPin;
use App\Domain\Setups\DevicePairing;
use App\Domain\Setups\PlayscopeRelease;
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

        $tenant = $currentTenant->tenant;

        return response()->json([
            'devices'   => $devices,
            'types'     => $types,
            'admin_pin' => [
                'is_set'     => (bool) $tenant?->device_admin_pin,
                'updated_at' => $tenant?->device_admin_pin_updated_at?->toIso8601String(),
            ],
            // Actieve Playscope-release: pc's met een andere versie werken zichzelf bij.
            'playscope_version' => PlayscopeRelease::current()['version'] ?? null,
        ]);
    }

    /**
     * Beheerpincode voor de toestellen van deze venue instellen (pin) of wissen (pin = null).
     * Playscope krijgt de nieuwe hash bij de volgende heartbeat (binnen een minuut).
     */
    public function updateAdminPin(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'pin' => ['nullable', 'string', 'regex:/^\d{' . DeviceAdminPin::MIN_LENGTH . ',' . DeviceAdminPin::MAX_LENGTH . '}$/'],
        ], [
            'pin.regex' => 'De pincode bestaat uit ' . DeviceAdminPin::MIN_LENGTH . ' tot ' . DeviceAdminPin::MAX_LENGTH . ' cijfers.',
        ]);

        $pin = $data['pin'] ?? null;
        $tenant = $currentTenant->tenant;
        $tenant->forceFill([
            'device_admin_pin'            => $pin === null ? null : DeviceAdminPin::hash($pin),
            'device_admin_pin_updated_at' => now(),
        ])->save();

        return response()->json([
            'is_set'     => $pin !== null,
            'updated_at' => $tenant->device_admin_pin_updated_at?->toIso8601String(),
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

        // Gedeeld met de staff-app (QR-code scannen), zie App\Domain\Setups\DevicePairing.
        $device = DevicePairing::claim($currentTenant->id(), $data['pairing_code'], $data['name'], $data['setup_id'] ?? null);

        if (! $device) {
            return response()->json(['message' => DevicePairing::NOT_FOUND], 422);
        }

        return response()->json($device->toPublicArray(), 201);
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

        // Playscope naar een andere speelplek verplaatst: de gamelijst van die speelplek meteen bijwerken.
        app(GameCatalog::class)->syncSetupGames($venueDevice->fresh());

        return response()->json($venueDevice->fresh('setup')->toPublicArray());
    }

    /**
     * Instellingen van een Playscope-toestel. idle_minutes: game afsluiten na zoveel minuten
     * zonder input (0 = uit, null = standaard). Het toestel krijgt ze bij de volgende heartbeat.
     */
    public function updateSettings(Request $request, CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $venueDevice);

        $data = $request->validate([
            'idle_minutes' => ['present', 'nullable', 'integer', 'min:0', 'max:' . VenueDevice::IDLE_MINUTES_MAX],
        ]);

        $minutes = $data['idle_minutes'];
        if ($minutes !== null && $minutes > 0 && $minutes < VenueDevice::IDLE_MINUTES_MIN) {
            return response()->json([
                'message' => 'Minstens ' . VenueDevice::IDLE_MINUTES_MIN . ' minuten, of 0 om uit te zetten.',
                'errors'  => ['idle_minutes' => ['Minstens ' . VenueDevice::IDLE_MINUTES_MIN . ' minuten, of 0 om uit te zetten.']],
            ], 422);
        }

        $venueDevice->forceFill(['idle_minutes' => $minutes])->save();

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

<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Setups\DeviceCommands;
use App\Http\Controllers\Controller;
use App\Models\VenueDevice;
use App\Models\VenueDeviceCommand;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bediening op afstand van een Playscope-pc (zie App\Domain\Setups\DeviceCommands).
 *
 *   GET  /api/backoffice/venue-devices/{id}/commands   laatste opdrachten met hun status
 *   POST /api/backoffice/venue-devices/{id}/commands   {command, payload?}
 */
class VenueDeviceCommandController extends Controller
{
    public function index(CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $venueDevice);

        $commands = $venueDevice->commands()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (VenueDeviceCommand $c) => $c->toPublicArray())
            ->values();

        return response()->json([
            'commands' => $commands,
            'device'   => $venueDevice->fresh('setup')->toPublicArray(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $venueDevice);
        abort_unless($venueDevice->type === 'playscope', 422, 'Enkel Playscope-pc\'s zijn op afstand te bedienen.');
        abort_unless($venueDevice->is_active, 422, 'Dit toestel is uitgeschakeld.');

        $data = $request->validate([
            // launch_game gebeurt via de frontdesk (Zaal), met de game uit de lijst van het toestel.
            'command'         => ['required', 'string', Rule::in(array_keys(DeviceCommands::COMMANDS)), Rule::notIn(['launch_game'])],
            'payload'         => ['nullable', 'array'],
            'payload.text'    => ['required_if:command,message', 'nullable', 'string', 'max:200'],
            'payload.seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
        ], [
            'payload.text.required_if' => 'Geef een bericht in.',
        ]);

        $payload = null;
        if ($data['command'] === 'message') {
            $payload = [
                'text'    => trim($data['payload']['text']),
                'seconds' => (int) ($data['payload']['seconds'] ?? 15),
            ];
        }

        $command = VenueDeviceCommand::create([
            'tenant_id'       => $currentTenant->id(),
            'venue_device_id' => $venueDevice->id,
            'command'         => $data['command'],
            'payload'         => $payload,
            'status'          => 'pending',
            'created_by'      => $request->user()?->id,
        ]);

        return response()->json($command->toPublicArray(), 201);
    }

    private function authorizeTenant(CurrentTenant $currentTenant, VenueDevice $device): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $device->tenant_id === (int) $currentTenant->id(), 404);
    }
}

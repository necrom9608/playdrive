<?php

namespace App\Http\Controllers\Api\Device;

use App\Domain\Games\GameCatalog;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\VenueDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Games van een Playscope-toestel (Bearer-token, middleware venue.device).
 *
 *   POST /api/device/v1/games  → geïnstalleerde games melden; antwoord = indeling
 *   GET  /api/device/v1/games  → enkel de indeling opnieuw ophalen
 *
 * Melden: { "source": "steam", "games": [ { "id": "620", "name": "Portal 2" }, ... ] }
 * Een lege lijst is geldig: dan staat er (van die bron) niets meer op het toestel.
 */
class DeviceGameController extends Controller
{
    public function __construct(private readonly GameCatalog $catalog)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        $data = $request->validate([
            'source'       => ['required', 'string', Rule::in(array_keys(Game::SOURCES))],
            'games'        => ['present', 'array', 'max:5000'],
            'games.*.id'   => ['required', 'string', 'max:64'],
            'games.*.name' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->catalog->report($device, $data['source'], $data['games']);

        return response()->json([
            'ok'       => true,
            'received' => $result['received'],
            'new'      => $result['new'],
            ...$this->catalog->payload($device->fresh('setup')),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        /** @var VenueDevice $device */
        $device = $request->attributes->get('venue_device');

        return response()->json($this->catalog->payload($device->loadMissing('setup')));
    }
}

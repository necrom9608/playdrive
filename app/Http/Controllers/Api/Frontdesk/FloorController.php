<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Games\GameArtwork;
use App\Domain\Setups\DeviceAdminPin;
use App\Domain\Setups\DeviceCommands;
use App\Domain\Setups\DeviceRotation;
use App\Domain\Setups\SetupTypes;
use App\Http\Controllers\Controller;
use App\Models\DeviceGroup;
use App\Models\Game;
use App\Models\PowerPlug;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Models\VenueDeviceCommand;
use App\Models\VenueDeviceGameSession;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Frontdesk → Zaal: live overzicht van de speelplekken en bediening tijdens de werking.
 * Inrichten (speelplekken, toestellen koppelen, groepen samenstellen) blijft in de backoffice.
 *
 *   GET  /floor                              speelplekken + toestellen + groepen + stekkers
 *   GET  /floor/devices/{id}/games           geïnstalleerde (zichtbare) games van een Playscope-pc
 *   POST /floor/devices/{id}/commands        {command, payload?, game_id?, pin?}
 *   GET  /floor/commands/{id}                status van een opdracht
 *   POST /floor/setups/{id}/status           {status: active|maintenance}
 *   POST /floor/groups/{id}/switch           {game_id?}  hele groep wisselen (of de volgende)
 *   POST /floor/groups/{id}/pause            {paused}    rotatie vastzetten / hervatten
 *   POST /floor/shutdown-pcs                 alle online Playscope-pc's netjes uitzetten (zaal sluiten)
 */
class FloorController extends Controller
{
    /** Opdrachten die de frontdesk mag geven (kioskmodus en updates blijven in de backoffice). */
    private const COMMANDS = ['stop_game', 'restart_app', 'message', 'launch_game', 'reboot', 'shutdown'];

    /** Deze vragen de beheerpincode (als die ingesteld is). */
    private const PIN_COMMANDS = ['reboot', 'shutdown'];

    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $setups = Setup::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', Setup::STATUS_ARCHIVED)
            ->with(['devices' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $plugs = PowerPlug::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('setup_id')
            ->whereNull('missing_since')
            ->orderBy('name')
            ->get()
            ->groupBy('setup_id');

        $groups = DeviceGroup::query()->where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->get();
        $members = VenueDevice::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'playscope')
            ->where('is_active', true)
            ->whereNotNull('device_group_id')
            ->orderBy('name')
            ->get();

        // Games op naam, om bij een spelende pc de banner van de game te vinden (Playscope meldt de naam).
        $gamesByName = Game::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'tenant_id', 'name', 'source', 'external_id', 'banner_path', 'banner_source', 'banner_ratio'])
            ->keyBy(fn (Game $g) => mb_strtolower(trim($g->name)));
        $gamesById = $gamesByName->keyBy('id');

        // Laatst gespeelde game per speelplek (laatste 30 dagen), voor de banner als er niets speelt.
        $deviceSetup = collect();
        foreach ($setups as $s) {
            foreach ($s->devices->where('type', 'playscope') as $d) {
                $deviceSetup[$d->id] = $s->id;   // toestel-id → speelplek-id
            }
        }
        $lastPlayed = collect();
        if ($deviceSetup->isNotEmpty()) {
            $lastPlayed = VenueDeviceGameSession::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('venue_device_id', $deviceSetup->keys())
                ->where('ended_at', '>=', now()->subDays(30))
                ->orderByDesc('ended_at')
                ->limit(500)
                ->get(['venue_device_id', 'game_id', 'game_name', 'ended_at'])
                ->unique(fn (VenueDeviceGameSession $s) => $deviceSetup[$s->venue_device_id])
                ->keyBy(fn (VenueDeviceGameSession $s) => $deviceSetup[$s->venue_device_id]);
        }

        return response()->json([
            'setups'        => $setups->map(fn (Setup $s) => $this->setupArray(
                $s,
                $plugs->get($s->id, collect()),
                $gamesByName,
                $this->lastPlayedBanner($lastPlayed->get($s->id), $gamesById, $gamesByName)
            ))->values(),
            'groups'        => $groups->map(fn (DeviceGroup $g) => $this->groupArray($g, $members))->values(),
            'admin_pin_set' => filled($currentTenant->tenant?->device_admin_pin),
            'server_time'   => now()->toIso8601String(),
        ]);
    }

    public function games(CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeDevice($currentTenant, $venueDevice);

        $games = $venueDevice->games()
            ->where('games.is_hidden', false)
            ->orderBy('games.name')
            ->get(['games.id', 'games.name', 'games.source', 'games.external_id']);

        return response()->json([
            'games' => $games->map(fn (Game $g) => [
                'id'           => $g->id,
                'name'         => $g->name,
                'source'       => $g->source,
                'source_label' => Game::SOURCES[$g->source] ?? $g->source,
                'cover_url'    => $g->coverUrl(),
            ])->values(),
        ]);
    }

    public function command(Request $request, CurrentTenant $currentTenant, VenueDevice $venueDevice): JsonResponse
    {
        $this->authorizeDevice($currentTenant, $venueDevice);
        abort_unless($venueDevice->type === 'playscope', 422, 'Enkel Playscope-pc\'s zijn op afstand te bedienen.');

        $data = $request->validate([
            'command'         => ['required', 'string', Rule::in(self::COMMANDS)],
            'payload'         => ['nullable', 'array'],
            'payload.text'    => ['required_if:command,message', 'nullable', 'string', 'max:200'],
            'payload.seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'game_id'         => ['required_if:command,launch_game', 'nullable', 'integer'],
            'pin'             => ['nullable', 'string', 'max:8'],
        ], [
            'payload.text.required_if' => 'Geef een bericht in.',
            'game_id.required_if'      => 'Kies een game.',
        ]);

        if (in_array($data['command'], self::PIN_COMMANDS, true)) {
            $this->checkPin($currentTenant, $data['pin'] ?? null);
        }

        $payload = null;
        if ($data['command'] === 'message') {
            $payload = [
                'text'    => trim($data['payload']['text']),
                'seconds' => (int) ($data['payload']['seconds'] ?? 15),
            ];
        } elseif ($data['command'] === 'launch_game') {
            abort_if($venueDevice->device_group_id, 422, 'Deze pc volgt de rotatie van zijn groep. Wissel via de groep.');

            $game = $venueDevice->games()->where('games.id', (int) $data['game_id'])->first();
            abort_unless($game, 422, 'Deze game staat niet op deze pc.');

            $payload = [
                'game_id' => $game->id,
                'source'  => $game->source,
                'id'      => $game->external_id,
                'name'    => $game->name,
            ];
        }

        $command = DeviceCommands::queue($venueDevice, $data['command'], $payload, $this->userId($request));

        return response()->json($command->toPublicArray(), 201);
    }

    public function commandStatus(CurrentTenant $currentTenant, VenueDeviceCommand $venueDeviceCommand): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $venueDeviceCommand->tenant_id === (int) $currentTenant->id(), 404);

        return response()->json($venueDeviceCommand->toPublicArray());
    }

    public function setStatus(Request $request, CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $setup->tenant_id === (int) $currentTenant->id(), 404);
        abort_if($setup->status === Setup::STATUS_ARCHIVED, 422, 'Deze speelplek is gearchiveerd.');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([Setup::STATUS_ACTIVE, Setup::STATUS_MAINTENANCE])],
        ]);

        $setup->forceFill(['status' => $data['status']])->save();

        return response()->json(['id' => $setup->id, 'status' => $setup->status]);
    }

    public function groupSwitch(Request $request, CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeGroup($currentTenant, $deviceGroup);
        $data = $request->validate(['game_id' => ['nullable', 'integer']]);

        if (isset($data['game_id'])) {
            abort_unless(in_array((int) $data['game_id'], $deviceGroup->playlistIds(), true), 422, 'Deze game staat niet in de afspeellijst van de groep.');
        }

        DeviceRotation::switchTo($deviceGroup, isset($data['game_id']) ? (int) $data['game_id'] : null);

        return response()->json(DeviceRotation::payload($deviceGroup->fresh()));
    }

    public function groupPause(Request $request, CurrentTenant $currentTenant, DeviceGroup $deviceGroup): JsonResponse
    {
        $this->authorizeGroup($currentTenant, $deviceGroup);
        $data = $request->validate(['paused' => ['required', 'boolean']]);

        DeviceRotation::setPaused($deviceGroup, (bool) $data['paused']);

        return response()->json(DeviceRotation::payload($deviceGroup->fresh()));
    }

    /** Zaal sluiten, stap 1: alle online Playscope-pc's netjes laten afsluiten (daarna gaan de stekkers uit). */
    public function shutdownPcs(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $devices = VenueDevice::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('type', 'playscope')
            ->where('is_active', true)
            ->where('last_seen_at', '>=', now()->subSeconds(VenueDevice::ONLINE_SECONDS))
            ->get();

        $userId = $this->userId($request);
        foreach ($devices as $device) {
            DeviceCommands::queue($device, 'shutdown', null, $userId);
        }

        return response()->json([
            'count'      => $devices->count(),
            'device_ids' => $devices->pluck('id')->values(),
        ]);
    }

    // ---------------------------------------------------------------- hulpjes

    /** Banner van de laatst gespeelde game, met de naam erbij ("Laatst: …" op de tegel). */
    private function lastPlayedBanner(?VenueDeviceGameSession $session, Collection $gamesById, Collection $gamesByName): ?array
    {
        if (! $session) {
            return null;
        }

        $game = ($session->game_id ? $gamesById->get($session->game_id) : null)
            ?? $gamesByName->get(mb_strtolower(trim((string) $session->game_name)));
        $banner = $game ? GameArtwork::bannerFor($game) : null;

        return $banner ? [...$banner, 'last_played' => $game->name ?: $session->game_name] : null;
    }

    private function setupArray(Setup $setup, Collection $plugs, ?Collection $gamesByName = null, ?array $lastPlayedBanner = null): array
    {
        $devices = $setup->devices;
        $playscope = $devices->where('type', 'playscope')->values();
        $live = $playscope->first(fn (VenueDevice $d) => $d->isOnline() && ($d->state['status'] ?? null) === 'playing');
        $grouped = $playscope->isNotEmpty() && $playscope->every(fn (VenueDevice $d) => $d->device_group_id);

        // Banner: de game die nu speelt, anders het eigen beeld van de speelplek (marquee, foto),
        // anders de laatst gespeelde game.
        $banner = null;
        $liveGame = $live?->state['game'] ?? null;
        if ($liveGame && $gamesByName && ($game = $gamesByName->get(mb_strtolower(trim((string) $liveGame))))) {
            $banner = GameArtwork::bannerFor($game);
        }
        $banner ??= GameArtwork::bannerFor($setup);
        // Niets aan het spelen en geen eigen beeld: de game die hier laatst speelde.
        $banner ??= $lastPlayedBanner;

        return [
            'id'           => $setup->id,
            'name'         => $setup->name,
            'type'         => $setup->type,
            'type_label'   => SetupTypes::label($setup->type),
            'zone'         => $setup->zone,
            'status'       => $setup->status,
            'max_players'  => $setup->max_players,
            'in_use'       => (bool) $live,
            'current_game' => $live?->state['game'] ?? null,
            'playing_since'=> $live?->state['since'] ?? null,
            'banner'       => $banner,
            'group_id'     => $grouped ? (int) $playscope->first()->device_group_id : null,
            'devices'      => $devices->map(fn (VenueDevice $d) => [
                'id'              => $d->id,
                'name'            => $d->name,
                'type'            => $d->type,
                'online'          => $d->isOnline(),
                'state'           => $d->state,
                'device_group_id' => $d->device_group_id,
                'app_version'     => $d->app_version,
                'last_seen_at'    => $d->last_seen_at?->toIso8601String(),
            ])->values(),
            'plugs'        => $plugs->map(fn (PowerPlug $p) => [
                'id'          => $p->id,
                'hub_id'      => $p->power_hub_id,
                'external_id' => $p->external_id,
                'name'        => $p->name,
            ])->values(),
        ];
    }

    private function groupArray(DeviceGroup $group, Collection $members): array
    {
        // Zoals in de backoffice: wisselen als het moet, ook als er geen toestel vraagt.
        DB::transaction(function () use ($group) {
            $locked = DeviceGroup::query()->lockForUpdate()->find($group->id);
            if ($locked) {
                DeviceRotation::advance($locked);
                $group->setRawAttributes($locked->getAttributes(), true);
            }
        });

        $fresh = now()->subSeconds(DeviceRotation::REPORT_FRESH_SECONDS);

        return [
            'id'       => $group->id,
            'name'     => $group->name,
            'status'   => DeviceRotation::payload($group),
            'playlist' => $group->games()->get(['games.id', 'games.name', 'games.source'])
                ->map(fn (Game $g) => ['id' => $g->id, 'name' => $g->name, 'source' => $g->source])
                ->values(),
            'members'  => $members->where('device_group_id', $group->id)->values()->map(function (VenueDevice $d) use ($fresh) {
                $reported = $d->rotation_reported_at && $d->rotation_reported_at->gte($fresh);
                $idle = $reported ? DeviceRotation::idleSeconds($d) : null;

                return [
                    'id'       => $d->id,
                    'name'     => $d->name,
                    'setup_id' => $d->setup_id,
                    'online'   => $d->isOnline(),
                    'phase'    => $reported ? ($d->rotation_report['phase'] ?? null) : null,
                    'busy'     => $idle !== null && $idle < DeviceRotation::SEAT_BUSY_SECONDS,
                ];
            })->values(),
        ];
    }

    private function checkPin(CurrentTenant $currentTenant, ?string $pin): void
    {
        $stored = $currentTenant->tenant?->device_admin_pin;
        if (! filled($stored)) {
            return; // geen pincode ingesteld: geen extra drempel
        }

        $key = 'floor-pin:' . $currentTenant->id();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Te veel foute pogingen. Probeer over een minuut opnieuw.');

        if (! DeviceAdminPin::verify((string) $pin, $stored)) {
            RateLimiter::hit($key, 60);
            abort(422, 'Verkeerde beheerpincode.');
        }

        RateLimiter::clear($key);
    }

    private function userId(Request $request): ?int
    {
        $user = $request->attributes->get('frontdesk_user');

        return $user?->id ? (int) $user->id : null;
    }

    private function authorizeDevice(CurrentTenant $currentTenant, VenueDevice $device): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $device->tenant_id === (int) $currentTenant->id(), 404);
        abort_unless($device->is_active, 422, 'Dit toestel is uitgeschakeld.');
    }

    private function authorizeGroup(CurrentTenant $currentTenant, DeviceGroup $group): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $group->tenant_id === (int) $currentTenant->id(), 404);
    }
}

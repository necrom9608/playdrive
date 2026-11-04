<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\PowerHub;
use App\Models\PowerPlug;
use App\Models\PowerWave;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Speelplekken → Stroom: DIRIGERA-hubs, opstartgolven en welke stekker bij welke speelplek hoort.
 * Koppelen van een hub en de stekkers uitlezen gebeurt aan de kassa (lokaal netwerk).
 *
 *   GET    /power                     hubs, golven, stekkers, speelplekken
 *   POST   /power/hubs                {name, host}
 *   PUT    /power/hubs/{id}           {name, host}
 *   POST   /power/hubs/{id}/unpair    token wissen
 *   DELETE /power/hubs/{id}
 *   PUT    /power/plugs/{id}          {setup_id, power_wave_id, wave_order}
 *   DELETE /power/plugs/{id}          (enkel stekkers die niet meer op de hub staan)
 *   POST   /power/waves               {name, stagger_seconds, delay_after_seconds}
 *   PUT    /power/waves/{id}
 *   DELETE /power/waves/{id}
 *   POST   /power/waves/reorder       {ids: [...]}
 */
class PowerController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $hubs = PowerHub::query()->where('tenant_id', $tenantId)->withCount('plugs')->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'hubs'   => $hubs->map(fn (PowerHub $h) => [...$h->toPublicArray(), 'plug_count' => (int) $h->plugs_count])->values(),
            'waves'  => $this->waves($tenantId),
            'plugs'  => PowerPlug::query()->where('tenant_id', $tenantId)->with('hub:id,name')
                ->orderBy('power_hub_id')->orderBy('name')->get()
                ->map(fn (PowerPlug $p) => [...$p->toPublicArray(), 'hub_name' => $p->hub?->name])->values(),
            'setups' => Setup::query()->where('tenant_id', $tenantId)->where('status', '!=', Setup::STATUS_ARCHIVED)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'zone'])
                ->map(fn (Setup $s) => ['id' => $s->id, 'name' => $s->name, 'zone' => $s->zone])->values(),
        ]);
    }

    public function storeHub(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();
        $data = $this->validateHub($request);

        $hub = DB::transaction(function () use ($data, $tenantId) {
            $hub = PowerHub::query()->create([
                ...$data,
                'tenant_id'  => $tenantId,
                'sort_order' => (int) PowerHub::query()->where('tenant_id', $tenantId)->max('sort_order') + 1,
            ]);

            // Eerste hub: meteen drie golven klaarzetten, aan te passen naar wens.
            if (! PowerWave::query()->where('tenant_id', $tenantId)->exists()) {
                foreach ([['Golf 1', 20], ['Golf 2', 20], ['Golf 3', 0]] as $i => [$name, $delay]) {
                    PowerWave::query()->create([
                        'tenant_id'           => $tenantId,
                        'name'                => $name,
                        'position'            => $i + 1,
                        'stagger_seconds'     => 2,
                        'delay_after_seconds' => $delay,
                    ]);
                }
            }

            return $hub;
        });

        return response()->json($hub->toPublicArray(), 201);
    }

    public function updateHub(Request $request, CurrentTenant $currentTenant, PowerHub $powerHub): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerHub->tenant_id);
        $powerHub->fill($this->validateHub($request))->save();

        return response()->json($powerHub->toPublicArray());
    }

    public function unpairHub(CurrentTenant $currentTenant, PowerHub $powerHub): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerHub->tenant_id);
        $powerHub->forceFill(['token' => null, 'paired_at' => null])->save();

        return response()->json($powerHub->toPublicArray());
    }

    public function destroyHub(CurrentTenant $currentTenant, PowerHub $powerHub): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerHub->tenant_id);
        $powerHub->delete(); // stekkers gaan mee (cascade)

        return response()->json(['ok' => true]);
    }

    public function updatePlug(Request $request, CurrentTenant $currentTenant, PowerPlug $powerPlug): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerPlug->tenant_id);
        $tenantId = $currentTenant->id();

        $data = $request->validate([
            'setup_id'      => ['nullable', 'integer', Rule::exists('setups', 'id')->where('tenant_id', $tenantId)],
            'power_wave_id' => ['nullable', 'integer', Rule::exists('power_waves', 'id')->where('tenant_id', $tenantId)],
            'wave_order'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $powerPlug->fill([
            'setup_id'      => $data['setup_id'] ?? null,
            'power_wave_id' => $data['power_wave_id'] ?? null,
            'wave_order'    => (int) ($data['wave_order'] ?? 0),
        ])->save();

        return response()->json([...$powerPlug->toPublicArray(), 'hub_name' => $powerPlug->hub?->name]);
    }

    /**
     * Elke stekker zonder speelplek krijgt er één: bestaat er al een speelplek met dezelfde naam,
     * dan wordt die gekoppeld; anders wordt ze aangemaakt (naam = stekker, zone = kamer op de hub).
     * Body (optioneel): {plug_ids: [...]} om het tot bepaalde stekkers te beperken.
     */
    public function createSetupsForPlugs(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();
        $data = $request->validate(['plug_ids' => ['nullable', 'array'], 'plug_ids.*' => ['integer']]);

        $result = DB::transaction(function () use ($tenantId, $data) {
            $plugs = PowerPlug::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('setup_id')
                ->whereNull('missing_since')
                ->when(! empty($data['plug_ids']), fn ($q) => $q->whereIn('id', $data['plug_ids']))
                ->orderBy('power_hub_id')->orderBy('name')
                ->get();

            $existing = Setup::query()->where('tenant_id', $tenantId)->where('status', '!=', Setup::STATUS_ARCHIVED)->get();
            $byName = $existing->keyBy(fn (Setup $s) => mb_strtolower(trim($s->name)));
            $zones = $existing->pluck('zone')->filter()->unique()->keyBy(fn ($z) => mb_strtolower(trim($z)));
            $sort = (int) Setup::query()->where('tenant_id', $tenantId)->max('sort_order');
            $linked = 0;
            $created = 0;

            foreach ($plugs as $plug) {
                $name = trim((string) $plug->name) ?: 'Stekker '.$plug->id;
                $key = mb_strtolower($name);
                $setup = $byName->get($key);

                if (! $setup) {
                    $room = trim((string) $plug->room);
                    $zone = $room === '' ? null : ($zones->get(mb_strtolower($room)) ?? $room);
                    if ($zone) {
                        $zones->put(mb_strtolower($zone), $zone);
                    }

                    $setup = Setup::query()->create([
                        'tenant_id'  => $tenantId,
                        'name'       => mb_substr($name, 0, 255),
                        'type'       => $this->guessSetupType($name.' '.$room),
                        'zone'       => $zone ? mb_substr($zone, 0, 100) : null,
                        'status'     => Setup::STATUS_ACTIVE,
                        'qr_code'    => Setup::generateQrCode(),
                        'sort_order' => ++$sort,
                    ]);
                    $byName->put($key, $setup);
                    $created++;
                } else {
                    $linked++;
                }

                $plug->update(['setup_id' => $setup->id]);
            }

            return ['created' => $created, 'linked' => $linked];
        });

        return response()->json($result);
    }

    /**
     * Stekkers automatisch over drie golven verdelen op basis van hun speelplek:
     *   golf 1  pc's: speelplek van het type PC of met een Playscope-pc (starten het traagst op)
     *   golf 2  kasten: arcadekasten, VR en flipperkasten (grootste piekstroom)
     *   golf 3  de rest: consoles/tv's, andere en stekkers zonder speelplek
     * Ontbrekende golven worden aangemaakt; golven die nog "Golf N" heten krijgen een naam en tijden.
     */
    public function autoWaves(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $plan = [
            ['name' => "PC's", 'stagger_seconds' => 2, 'delay_after_seconds' => 15],
            ['name' => 'Kasten', 'stagger_seconds' => 2, 'delay_after_seconds' => 15],
            ['name' => 'Rest', 'stagger_seconds' => 1, 'delay_after_seconds' => 0],
        ];

        $result = DB::transaction(function () use ($tenantId, $plan) {
            $waves = PowerWave::query()->where('tenant_id', $tenantId)->orderBy('position')->orderBy('id')->get()->values();
            $position = (int) $waves->max('position');

            foreach ($plan as $i => $def) {
                $wave = $waves->get($i);
                if (! $wave) {
                    $wave = PowerWave::query()->create([...$def, 'tenant_id' => $tenantId, 'position' => ++$position]);
                    $waves->put($i, $wave);
                } elseif (preg_match('/^golf\s*\d+$/i', trim((string) $wave->name))) {
                    $wave->update($def);
                }
            }

            $playscopeSetups = VenueDevice::query()->where('tenant_id', $tenantId)
                ->where('type', 'playscope')->whereNotNull('setup_id')->pluck('setup_id')->flip();

            $plugs = PowerPlug::query()->where('tenant_id', $tenantId)->whereNull('missing_since')
                ->with('setup:id,type,zone,name')->get()
                ->sortBy(fn (PowerPlug $p) => mb_strtolower(($p->setup?->zone ?? $p->room ?? '').' '.($p->setup?->name ?? $p->name)))
                ->values();

            $order = [0, 0, 0];
            $counts = [0, 0, 0];
            foreach ($plugs as $plug) {
                $setup = $plug->setup;
                $index = match (true) {
                    $setup && ($setup->type === 'pc' || $playscopeSetups->has($setup->id)) => 0,
                    $setup && in_array($setup->type, ['arcade', 'vr', 'pinball'], true)  => 1,
                    default                                                             => 2,
                };
                $plug->update(['power_wave_id' => $waves[$index]->id, 'wave_order' => $order[$index]++]);
                $counts[$index]++;
            }

            return $counts;
        });

        return response()->json([
            'counts' => $result,
            'waves'  => $this->waves($tenantId),
        ]);
    }

    /** Soort speelplek raden uit naam + kamer van de stekker; aanpasbaar in Speelplekken. */
    private function guessSetupType(string $text): string
    {
        $t = mb_strtolower($text);

        return match (true) {
            (bool) preg_match('/\b(pc|lan|computer|gaming ?pc)\s*\d*\b/u', $t) => 'pc',
            (bool) preg_match('/\bvr\b|virtual|oculus|quest|vive/u', $t)       => 'vr',
            (bool) preg_match('/flipper|pinball/u', $t)                         => 'pinball',
            (bool) preg_match('/\b(ps[1-5]?|playstation|xbox|switch|nintendo|wii|couch|console|sega saturn|dreamcast|gamecube|n64|snes|nes|tv)\b/u', $t) => 'console',
            default                                                             => 'arcade',
        };
    }

    public function destroyPlug(CurrentTenant $currentTenant, PowerPlug $powerPlug): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerPlug->tenant_id);
        abort_unless($powerPlug->missing_since !== null, 422, 'Deze stekker staat nog op de hub; hij komt bij het volgende uitlezen terug.');
        $powerPlug->delete();

        return response()->json(['ok' => true]);
    }

    public function storeWave(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        PowerWave::query()->create([
            ...$this->validateWave($request),
            'tenant_id' => $tenantId,
            'position'  => (int) PowerWave::query()->where('tenant_id', $tenantId)->max('position') + 1,
        ]);

        return response()->json(['waves' => $this->waves($tenantId)], 201);
    }

    public function updateWave(Request $request, CurrentTenant $currentTenant, PowerWave $powerWave): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerWave->tenant_id);
        $powerWave->fill($this->validateWave($request))->save();

        return response()->json(['waves' => $this->waves($currentTenant->id())]);
    }

    public function destroyWave(CurrentTenant $currentTenant, PowerWave $powerWave): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $powerWave->tenant_id);
        $powerWave->delete(); // stekkers: power_wave_id → null (niet meer in de opstartsequentie)

        return response()->json(['waves' => $this->waves($currentTenant->id())]);
    }

    public function reorderWaves(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        DB::transaction(function () use ($data, $tenantId) {
            foreach (array_values($data['ids']) as $i => $id) {
                PowerWave::query()->where('tenant_id', $tenantId)->whereKey((int) $id)->update(['position' => $i + 1]);
            }
        });

        return response()->json(['waves' => $this->waves($tenantId)]);
    }

    // ---------------------------------------------------------------- hulpjes

    private function waves(int $tenantId): array
    {
        return PowerWave::query()->where('tenant_id', $tenantId)->orderBy('position')->orderBy('id')->get()
            ->map(fn (PowerWave $w) => $w->toPublicArray())->values()->all();
    }

    private function validateHub(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
        ], [
            'host.regex' => 'Geef een IP-adres (bv. 192.168.1.50) of hostnaam in, zonder https:// of poort.',
        ]);
    }

    private function validateWave(Request $request): array
    {
        return $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'stagger_seconds'     => ['required', 'integer', 'min:0', 'max:' . PowerWave::STAGGER_MAX],
            'delay_after_seconds' => ['required', 'integer', 'min:0', 'max:' . PowerWave::DELAY_MAX],
        ]);
    }

    private function authorizeTenant(CurrentTenant $currentTenant, $tenantId): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $tenantId === (int) $currentTenant->id(), 404);
    }
}

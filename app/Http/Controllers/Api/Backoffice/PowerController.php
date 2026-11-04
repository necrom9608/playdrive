<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\PowerHub;
use App\Models\PowerPlug;
use App\Models\PowerWave;
use App\Models\Setup;
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

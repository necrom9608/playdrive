<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Setups\SetupTypes;
use App\Http\Controllers\Controller;
use App\Models\Setup;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Beheer van speelplekken (arcadekasten, LAN-PC's, couch-setups, ...). */
class SetupController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $setups = Setup::query()
            ->where('tenant_id', $currentTenant->id())
            ->with('devices')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Setup $s) => $this->map($s))
            ->values();

        return response()->json([
            'setups'   => $setups,
            'types'    => SetupTypes::all(),
            'statuses' => SetupTypes::statuses(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $this->validateSetup($request);

        $nextSort = (int) Setup::query()->where('tenant_id', $currentTenant->id())->max('sort_order');

        $setup = Setup::query()->create([
            ...$data,
            'tenant_id'  => $currentTenant->id(),
            'games'      => $this->cleanGames($data['games'] ?? []),
            'status'     => $data['status'] ?? Setup::STATUS_ACTIVE,
            'qr_code'    => Setup::generateQrCode(),
            'sort_order' => $nextSort + 1,
        ]);

        return response()->json($this->map($setup->load('devices')), 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $setup);

        $data = $this->validateSetup($request);

        $setup->fill([
            ...$data,
            'games'  => $this->cleanGames($data['games'] ?? []),
            'status' => $data['status'] ?? $setup->status,
        ])->save();

        return response()->json($this->map($setup->fresh('devices')));
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'items'      => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($data, $currentTenant) {
            foreach (array_values($data['items']) as $index => $item) {
                Setup::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', (int) $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Nieuwe QR-code — de oude (eventueel geprinte) code werkt daarna niet meer. */
    public function regenerateQr(CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $setup);

        $setup->update(['qr_code' => Setup::generateQrCode()]);

        return response()->json($this->map($setup->fresh('devices')));
    }

    public function destroy(CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorizeTenant($currentTenant, $setup);

        // Toestellen blijven bestaan, enkel de koppeling verdwijnt (nullOnDelete).
        $setup->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeTenant(CurrentTenant $currentTenant, Setup $setup): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $setup->tenant_id === (int) $currentTenant->id(), 404);
    }

    private function validateSetup(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'string', Rule::in(SetupTypes::keys())],
            'zone'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'games'       => ['nullable', 'array', 'max:500'],
            'games.*'     => ['nullable', 'string', 'max:255'],
            'max_players' => ['nullable', 'integer', 'min:1', 'max:64'],
            'status'      => ['nullable', 'string', Rule::in(array_keys(SetupTypes::STATUSES))],
        ]);
    }

    private function cleanGames(array $games): array
    {
        return collect($games)
            ->map(fn ($g) => trim((string) $g))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function map(Setup $setup): array
    {
        $devices = $setup->relationLoaded('devices') ? $setup->devices : collect();
        $live = $devices->first(fn (VenueDevice $d) => $d->isOnline() && ($d->state['status'] ?? null) === 'playing');

        return [
            'id'          => $setup->id,
            'name'        => $setup->name,
            'type'        => $setup->type,
            'type_label'  => SetupTypes::label($setup->type),
            'zone'        => $setup->zone,
            'description' => $setup->description,
            'games'       => $setup->games ?? [],
            'max_players' => $setup->max_players,
            'status'      => $setup->status,
            'qr_code'     => $setup->qr_code,
            'qr_url'      => $setup->qrUrl(),
            'sort_order'  => (int) $setup->sort_order,
            'in_use'      => (bool) $live,
            'current_game'=> $live?->state['game'] ?? null,
            'devices'     => $devices->map(fn (VenueDevice $d) => [
                'id'     => $d->id,
                'name'   => $d->name,
                'type'   => $d->type,
                'online' => $d->isOnline(),
            ])->values(),
        ];
    }
}

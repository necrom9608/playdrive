<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\RosterRole;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RosterRoleController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $roles = RosterRole::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RosterRole $r) => $this->map($r))
            ->values();

        return response()->json($roles);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $this->validateRole($request);

        $nextSort = (int) RosterRole::query()
            ->where('tenant_id', $currentTenant->id())
            ->max('sort_order');

        $role = RosterRole::query()->create([
            'tenant_id'  => $currentTenant->id(),
            'name'       => $data['name'],
            'color'      => $data['color'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'sort_order' => $nextSort + 1,
        ]);

        return response()->json($this->map($role), 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, RosterRole $role): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $role->tenant_id === (int) $currentTenant->id(), 404);

        $data = $this->validateRole($request);

        $role->fill([
            'name'      => $data['name'],
            'color'     => $data['color'] ?? null,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);
        $role->save();

        return response()->json($this->map($role->fresh()));
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'items'      => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $ids = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->values();

        DB::transaction(function () use ($ids, $currentTenant) {
            foreach ($ids as $index => $id) {
                RosterRole::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Verwijdert een rol. Weigert als de rol nog op slots of shiften gebruikt
     * wordt — zet hem in dat geval op inactief (via update).
     */
    public function destroy(CurrentTenant $currentTenant, RosterRole $role): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $role->tenant_id === (int) $currentTenant->id(), 404);

        $inUse = RosterSlot::query()->where('role_id', $role->id)->exists()
            || RosterShift::query()->where('role_id', $role->id)->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Deze rol is nog in gebruik. Zet hem op inactief in plaats van te verwijderen.',
            ], 422);
        }

        $role->delete();

        return response()->json(['ok' => true]);
    }

    private function validateRole(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function map(RosterRole $role): array
    {
        return [
            'id'         => $role->id,
            'name'       => $role->name,
            'color'      => $role->color,
            'sort_order' => (int) $role->sort_order,
            'is_active'  => (bool) $role->is_active,
        ];
    }
}

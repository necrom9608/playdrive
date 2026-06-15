<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\RosterAssignment;
use App\Models\RosterRole;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Models\RosterSlotDefault;
use App\Models\User;
use App\Services\RosterService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RosterController extends Controller
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    // ==================================================================
    //  Basisdata
    // ==================================================================

    /**
     * Gedeelde data voor de roostereditor: rollen, medewerkers en de
     * seizoenen van de regio (voor de seizoenkiezer).
     */
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenant = $currentTenant->tenant;

        return response()->json([
            'roles'   => $this->mapRoles($tenant->id),
            'staff'   => $this->mapStaff($tenant->id),
            'seasons' => $this->mapSeasons($tenant->region_code),
        ]);
    }

    // ==================================================================
    //  Algemeen rooster (slots)
    // ==================================================================

    public function slots(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $data = $request->validate([
            'season_key' => ['required', 'string', 'max:50'],
        ]);

        $defaults = RosterSlotDefault::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->groupBy('slot_id');

        $slots = RosterSlot::query()
            ->where('tenant_id', $tenantId)
            ->where('season_key', $data['season_key'])
            ->orderBy('weekday')
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RosterSlot $s) => $this->mapSlot($s, $defaults->get($s->id)))
            ->values();

        return response()->json(['slots' => $slots]);
    }

    public function storeSlot(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $data = $this->validateSlot($request, $tenantId);

        $slot = DB::transaction(function () use ($tenantId, $data) {
            $nextSort = (int) RosterSlot::query()
                ->where('tenant_id', $tenantId)
                ->where('season_key', $data['season_key'])
                ->where('weekday', $data['weekday'])
                ->max('sort_order');

            $slot = RosterSlot::query()->create([
                'tenant_id'     => $tenantId,
                'season_key'    => $data['season_key'],
                'weekday'       => $data['weekday'],
                'role_id'       => $data['role_id'] ?? null,
                'starts_at'     => $data['starts_at'],
                'ends_at'       => $data['ends_at'],
                'desired_count' => $data['desired_count'] ?? null,
                'comment'       => $data['comment'] ?? null,
                'sort_order'    => $nextSort + 1,
            ]);

            $this->syncDefaults($tenantId, $slot->id, $data['default_user_ids'] ?? []);

            return $slot;
        });

        $defaults = RosterSlotDefault::query()->where('slot_id', $slot->id)->get();

        return response()->json(['slot' => $this->mapSlot($slot, $defaults)], 201);
    }

    public function updateSlot(Request $request, CurrentTenant $currentTenant, RosterSlot $slot): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $slot->tenant_id === (int) $currentTenant->id(), 404);
        $tenantId = $currentTenant->id();

        $data = $this->validateSlot($request, $tenantId);

        DB::transaction(function () use ($slot, $data, $tenantId) {
            $slot->fill([
                'season_key'    => $data['season_key'],
                'weekday'       => $data['weekday'],
                'role_id'       => $data['role_id'] ?? null,
                'starts_at'     => $data['starts_at'],
                'ends_at'       => $data['ends_at'],
                'desired_count' => $data['desired_count'] ?? null,
                'comment'       => $data['comment'] ?? null,
            ]);
            $slot->save();

            $this->syncDefaults($tenantId, $slot->id, $data['default_user_ids'] ?? []);
        });

        $defaults = RosterSlotDefault::query()->where('slot_id', $slot->id)->get();

        return response()->json(['slot' => $this->mapSlot($slot->fresh(), $defaults)]);
    }

    public function destroySlot(CurrentTenant $currentTenant, RosterSlot $slot): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $slot->tenant_id === (int) $currentTenant->id(), 404);

        DB::transaction(function () use ($slot) {
            RosterSlotDefault::query()->where('slot_id', $slot->id)->delete();
            $slot->delete();
        });

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    //  Weekplanning
    // ==================================================================

    public function week(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'week_start' => ['required', 'date'],
        ]);

        return response()->json($this->buildWeek($currentTenant, $data['week_start']));
    }

    public function generateWeek(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = $this->rosters->weekStart($data['week_start']);
        $created   = $this->rosters->generateWeek($currentTenant->tenant, $weekStart);

        $week = $this->buildWeek($currentTenant, $data['week_start']);
        $week['created'] = $created;

        return response()->json($week);
    }

    public function resetWeek(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'week_start' => ['required', 'date'],
        ]);

        $weekStart = $this->rosters->weekStart($data['week_start']);
        $this->rosters->resetWeek($currentTenant->tenant, $weekStart);

        return response()->json($this->buildWeek($currentTenant, $data['week_start']));
    }

    // ------------------------------------------------------------------
    //  Losse / aangepaste shiften
    // ------------------------------------------------------------------

    public function storeShift(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $data = $this->validateShift($request, $tenantId);

        $shift = RosterShift::query()->create([
            'tenant_id'     => $tenantId,
            'date'          => $data['date'],
            'season_key'    => null,
            'slot_id'       => null,
            'role_id'       => $data['role_id'] ?? null,
            'starts_at'     => $data['starts_at'],
            'ends_at'       => $data['ends_at'],
            'desired_count' => $data['desired_count'] ?? null,
            'comment'       => $data['comment'] ?? null,
            'note'          => $data['note'] ?? null,
            'status'        => 'scheduled',
            'source'        => RosterShift::SOURCE_MANUAL,
        ]);

        return response()->json(['shift' => $this->mapShift($shift->load('assignments'), $this->staffNames($tenantId))], 201);
    }

    public function updateShift(Request $request, CurrentTenant $currentTenant, RosterShift $shift): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $shift->tenant_id === (int) $currentTenant->id(), 404);
        $tenantId = $currentTenant->id();

        $data = $this->validateShift($request, $tenantId, dateRequired: false);

        $shift->fill([
            'role_id'       => $data['role_id'] ?? null,
            'starts_at'     => $data['starts_at'],
            'ends_at'       => $data['ends_at'],
            'desired_count' => $data['desired_count'] ?? null,
            'comment'       => $data['comment'] ?? null,
            'note'          => $data['note'] ?? null,
            'status'        => $data['status'] ?? $shift->status,
        ]);
        $shift->save();

        return response()->json(['shift' => $this->mapShift($shift->fresh()->load('assignments'), $this->staffNames($tenantId))]);
    }

    public function destroyShift(CurrentTenant $currentTenant, RosterShift $shift): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $shift->tenant_id === (int) $currentTenant->id(), 404);

        DB::transaction(function () use ($shift) {
            RosterAssignment::query()->where('shift_id', $shift->id)->delete();
            $shift->delete();
        });

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    //  Toewijzingen (persoon-in-blok)
    // ------------------------------------------------------------------

    public function addAssignment(Request $request, CurrentTenant $currentTenant, RosterShift $shift): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $shift->tenant_id === (int) $currentTenant->id(), 404);
        $tenantId = $currentTenant->id();

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $this->assertStaffInTenant($data['user_id'], $tenantId);

        $assignment = RosterAssignment::query()->firstOrCreate(
            ['shift_id' => $shift->id, 'user_id' => (int) $data['user_id']],
            ['tenant_id' => $tenantId, 'source' => RosterAssignment::SOURCE_MANUAL],
        );

        return response()->json([
            'assignment' => $this->mapAssignment($assignment, $this->staffNames($tenantId)),
        ], 201);
    }

    public function removeAssignment(CurrentTenant $currentTenant, RosterAssignment $assignment): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $assignment->tenant_id === (int) $currentTenant->id(), 404);

        $assignment->delete();

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function buildWeek(CurrentTenant $currentTenant, string $weekStartInput): array
    {
        $tenant    = $currentTenant->tenant;
        $weekStart = $this->rosters->weekStart($weekStartInput);
        $dates     = $this->rosters->weekDates($weekStart);
        $seasonOf  = $this->rosters->seasonMap($dates, $tenant->region_code);

        $staffNames = $this->staffNames($tenant->id);
        $shifts     = $this->rosters->weekShifts($tenant->id, $weekStart);

        $days = [];
        foreach ($dates as $d) {
            $days[] = ['date' => $d, 'season_key' => $seasonOf[$d] ?? 'regular'];
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'days'       => $days,
            'roles'      => $this->mapRoles($tenant->id),
            'staff'      => $this->mapStaff($tenant->id),
            'shifts'     => $shifts->map(fn (RosterShift $s) => $this->mapShift($s, $staffNames))->values(),
        ];
    }

    private function syncDefaults(int $tenantId, int $slotId, array $userIds): void
    {
        RosterSlotDefault::query()->where('slot_id', $slotId)->delete();

        $valid = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', $userIds))
            ->pluck('id');

        foreach ($valid as $uid) {
            RosterSlotDefault::query()->create([
                'tenant_id' => $tenantId,
                'slot_id'   => $slotId,
                'user_id'   => $uid,
            ]);
        }
    }

    private function validateSlot(Request $request, int $tenantId): array
    {
        $data = $request->validate([
            'season_key'        => ['required', 'string', 'max:50'],
            'weekday'           => ['required', 'integer', 'min:1', 'max:7'],
            'role_id'           => ['nullable', 'integer'],
            'starts_at'         => ['required', 'date_format:H:i'],
            'ends_at'           => ['required', 'date_format:H:i'],
            'desired_count'     => ['nullable', 'integer', 'min:1', 'max:99'],
            'comment'           => ['nullable', 'string', 'max:255'],
            'default_user_ids'  => ['nullable', 'array'],
            'default_user_ids.*'=> ['integer'],
        ]);

        if (!empty($data['role_id'])) {
            $this->assertRoleInTenant($data['role_id'], $tenantId);
        }

        return $data;
    }

    private function validateShift(Request $request, int $tenantId, bool $dateRequired = true): array
    {
        $rules = [
            'role_id'       => ['nullable', 'integer'],
            'starts_at'     => ['required', 'date_format:H:i'],
            'ends_at'       => ['required', 'date_format:H:i'],
            'desired_count' => ['nullable', 'integer', 'min:1', 'max:99'],
            'comment'       => ['nullable', 'string', 'max:255'],
            'note'          => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'in:scheduled,confirmed,cancelled'],
        ];
        if ($dateRequired) {
            $rules['date'] = ['required', 'date'];
        }

        $data = $request->validate($rules);

        if (!empty($data['role_id'])) {
            $this->assertRoleInTenant($data['role_id'], $tenantId);
        }

        return $data;
    }

    private function assertStaffInTenant(int|string $userId, int $tenantId): void
    {
        $ok = User::query()->where('id', (int) $userId)->where('tenant_id', $tenantId)->exists();
        abort_unless($ok, 422, 'Medewerker hoort niet bij deze tenant.');
    }

    private function assertRoleInTenant(int|string $roleId, int $tenantId): void
    {
        $ok = RosterRole::query()->where('id', (int) $roleId)->where('tenant_id', $tenantId)->exists();
        abort_unless($ok, 422, 'Rol hoort niet bij deze tenant.');
    }

    /** Map [user_id => name] voor toewijzing-weergave. */
    private function staffNames(int $tenantId): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->pluck('name', 'id')
            ->all();
    }

    private function mapRoles(int $tenantId): array
    {
        return RosterRole::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RosterRole $r) => [
                'id'        => $r->id,
                'name'      => $r->name,
                'color'     => $r->color,
                'is_active' => (bool) $r->is_active,
            ])
            ->values()
            ->all();
    }

    private function mapStaff(int $tenantId): array
    {
        return $this->rosters->staff($tenantId)
            ->map(fn (User $u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'is_active' => (bool) $u->is_active,
            ])
            ->values()
            ->all();
    }

    private function mapSeasons(?string $regionCode): array
    {
        return $this->rosters->seasonsForRegion($regionCode)
            ->map(fn ($s) => [
                'season_key'  => $s->season_key,
                'season_name' => $s->season_name,
                'date_from'   => $s->date_from?->toDateString(),
                'date_until'  => $s->date_until?->toDateString(),
                'priority'    => $s->priority,
            ])
            ->values()
            ->all();
    }

    private function mapSlot(RosterSlot $slot, $defaults): array
    {
        $defaults = $defaults ?? collect();

        return [
            'id'               => $slot->id,
            'season_key'       => $slot->season_key,
            'weekday'          => $slot->weekday,
            'role_id'          => $slot->role_id,
            'starts_at'        => $this->hm($slot->starts_at),
            'ends_at'          => $this->hm($slot->ends_at),
            'desired_count'    => $slot->desired_count,
            'comment'          => $slot->comment,
            'sort_order'       => $slot->sort_order,
            'default_user_ids' => collect($defaults)->pluck('user_id')->map(fn ($i) => (int) $i)->values()->all(),
        ];
    }

    private function mapShift(RosterShift $shift, array $staffNames): array
    {
        $assignments = $shift->assignments->map(fn (RosterAssignment $a) => $this->mapAssignment($a, $staffNames))->values();

        return [
            'id'            => $shift->id,
            'date'          => $shift->date?->toDateString(),
            'season_key'    => $shift->season_key,
            'role_id'       => $shift->role_id,
            'starts_at'     => $this->hm($shift->starts_at),
            'ends_at'       => $this->hm($shift->ends_at),
            'desired_count' => $shift->desired_count,
            'comment'       => $shift->comment,
            'note'          => $shift->note,
            'status'        => $shift->status,
            'source'        => $shift->source,
            'assignments'   => $assignments,
            'filled_count'  => $assignments->count(),
        ];
    }

    private function mapAssignment(RosterAssignment $a, array $staffNames): array
    {
        return [
            'id'      => $a->id,
            'user_id' => $a->user_id,
            'name'    => $staffNames[$a->user_id] ?? ('#' . $a->user_id),
            'source'  => $a->source,
        ];
    }

    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}

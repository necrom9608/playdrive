<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\RosterAssignment;
use App\Models\RosterShift;
use App\Models\User;
use App\Services\RosterService;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-zicht op het uurrooster: de ingelogde medewerker ziet ENKEL de eigen
 * shiften (blokken waarin hij/zij is toegewezen), week per week. Read-only —
 * plannen gebeurt in de backoffice. Per shift tonen we tijd, rol (met kleur),
 * staand commentaar + dagnotitie en de collega's in hetzelfde blok.
 */
class RosterController extends Controller
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user     = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();

        $anchor    = $request->filled('week_start')
            ? $request->string('week_start')->toString()
            : now()->toDateString();
        $weekStart = $this->rosters->weekStart($anchor);
        $weekEnd   = $weekStart->addDays(6);
        $dates     = $this->rosters->weekDates($weekStart);

        // Enkel shiften van deze week waarin DEZE medewerker is toegewezen.
        $shifts = RosterShift::query()
            ->with(['role', 'assignments'])
            ->where('tenant_id', $tenantId)
            ->whereIn('date', $dates)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('date')
            ->orderBy('starts_at')
            ->orderBy('sort_order')
            ->get();

        $staffNames = $this->staffNames($tenantId);

        // Groepeer per dag.
        $byDate = $shifts->groupBy(fn (RosterShift $s) => $s->date?->toDateString());

        $today      = now()->toDateString();
        $totalMin   = 0;
        $shiftCount = 0;
        $days       = [];

        foreach ($dates as $date) {
            $cursor   = Carbon::parse($date);
            $dayItems = ($byDate->get($date) ?? collect())
                ->map(function (RosterShift $shift) use ($user, $staffNames, &$totalMin, &$shiftCount) {
                    $minutes = $this->minutesBetween($shift->starts_at, $shift->ends_at);
                    $totalMin += $minutes;
                    $shiftCount++;

                    $colleagues = $shift->assignments
                        ->filter(fn (RosterAssignment $a) => (int) $a->user_id !== (int) $user->id)
                        ->map(fn (RosterAssignment $a) => $staffNames[$a->user_id] ?? ('#' . $a->user_id))
                        ->values()
                        ->all();

                    return [
                        'id'              => $shift->id,
                        'starts_at'       => $this->hm($shift->starts_at),
                        'ends_at'         => $this->hm($shift->ends_at),
                        'time_label'      => $this->hm($shift->starts_at) . ' – ' . $this->hm($shift->ends_at),
                        'duration_label'  => $this->durationLabel($minutes),
                        'role'            => $shift->role ? [
                            'name'  => $shift->role->name,
                            'color' => $shift->role->color,
                        ] : null,
                        'comment'         => $shift->comment,
                        'note'            => $shift->note,
                        'status'          => $shift->status,
                        'colleagues'      => $colleagues,
                    ];
                })
                ->values()
                ->all();

            $days[] = [
                'date'          => $date,
                'weekday_label' => ucfirst($cursor->locale('nl_BE')->isoFormat('dddd')),
                'weekday_short' => ucfirst($cursor->locale('nl_BE')->isoFormat('dd')),
                'day_label'     => ucfirst($cursor->locale('nl_BE')->isoFormat('ddd D MMM')),
                'is_today'      => $date === $today,
                'shifts'        => $dayItems,
            ];
        }

        return response()->json([
            'data' => [
                'week_start'  => $weekStart->toDateString(),
                'week_end'    => $weekEnd->toDateString(),
                'range_label' => $weekStart->locale('nl_BE')->isoFormat('D MMM')
                    . ' – ' . $weekEnd->locale('nl_BE')->isoFormat('D MMM YYYY'),
                'is_current_week' => $weekStart->lessThanOrEqualTo(now())
                    && $weekEnd->greaterThanOrEqualTo(now()->startOfDay()),
                'totals' => [
                    'shifts'      => $shiftCount,
                    'minutes'     => $totalMin,
                    'hours_label' => $this->durationLabel($totalMin),
                ],
                'days' => $days,
            ],
        ]);
    }

    /** [user_id => name] voor collega-weergave. */
    private function staffNames(int $tenantId): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->pluck('name', 'id')
            ->all();
    }

    /** Minuten tussen twee TIME-strings; overnacht (eind < start) telt door. */
    private function minutesBetween(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        [$sh, $sm] = array_pad(array_map('intval', explode(':', $start)), 2, 0);
        [$eh, $em] = array_pad(array_map('intval', explode(':', $end)), 2, 0);

        $s = $sh * 60 + $sm;
        $e = $eh * 60 + $em;
        if ($e < $s) {
            $e += 24 * 60;
        }

        return max(0, $e - $s);
    }

    private function durationLabel(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h > 0 && $m > 0) {
            return $h . 'u' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        }
        if ($h > 0) {
            return $h . 'u';
        }

        return $m . 'min';
    }

    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}

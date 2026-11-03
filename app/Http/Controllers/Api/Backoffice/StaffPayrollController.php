<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\RosterShift;
use App\Models\StaffAbsenceDay;
use App\Models\StaffCheckin;
use App\Models\StaffPayrollMonth;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Maandprestaties: gewerkte uren per medewerker per dag volgens de check-ins,
 * in een maandkalender om over te nemen in het sociaal secretariaat (Liantis).
 *
 * Uren zijn exact (geen afronding): per sessie het verschil tussen in- en
 * uitchecktijd, beide afgekapt op de minuut — dus gelijk aan wat op het scherm staat.
 */
class StaffPayrollController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();

        $validated = $request->validate([
            'month'    => ['nullable', 'date_format:Y-m'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $period = $validated['month'] ?? $this->defaultPeriod();
        [$start, $end] = $this->periodRange($period);

        $sessions = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('checked_in_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $start))
            ->orderBy('checked_in_at')
            ->get();

        $absences = StaffAbsenceDay::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $months = StaffPayrollMonth::query()
            ->where('tenant_id', $tenantId)
            ->where('period', $period)
            ->get()
            ->keyBy('user_id');

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $holidays = $this->belgianHolidays((int) $start->year);

        $staff = $users
            ->filter(fn (User $u) => $u->is_active
                || $sessions->contains('user_id', $u->id)
                || $absences->contains('user_id', $u->id))
            ->map(function (User $u) use ($sessions, $absences, $months, $start, $end, $holidays) {
                $days = $this->buildDays(
                    $start, $end,
                    $sessions->where('user_id', $u->id)->values(),
                    $absences->where('user_id', $u->id)->values(),
                    collect(), collect(), $holidays,
                );

                return $this->summary($u, $days, $months->get($u->id));
            })
            ->values();

        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;
        if (! $staffId || ! $staff->contains('id', $staffId)) {
            $staffId = $staff->first(fn ($s) => $s['worked_minutes'] > 0)['id'] ?? $staff->first()['id'] ?? null;
        }

        $selected = null;
        if ($staffId) {
            $days = $this->buildDays(
                $start, $end,
                $sessions->where('user_id', $staffId)->values(),
                $absences->where('user_id', $staffId)->values(),
                $this->rosterFor($tenantId, $staffId, $start, $end),
                $this->leaveFor($tenantId, $staffId, $start, $end),
                $holidays,
            );

            $month = $months->get($staffId);
            $selected = [
                'summary' => $staff->firstWhere('id', $staffId),
                'days'    => array_values($days),
                'submitted_by_name' => $month?->submitted_by ? User::query()->whereKey($month->submitted_by)->value('name') : null,
            ];
        }

        return response()->json([
            'month'         => $period,
            'month_label'   => $this->monthLabel($start),
            'absence_codes' => collect(StaffAbsenceDay::CODES)->map(fn ($v, $k) => ['code' => $k] + $v)->values(),
            'staff'         => $staff,
            'staff_id'      => $staffId,
            'selected'      => $selected,
        ]);
    }

    /** Afwezigheidscode op een dag zetten (code = null → verwijderen). */
    public function setAbsence(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'date'    => ['required', 'date_format:Y-m-d'],
            'code'    => ['nullable', Rule::in(array_keys(StaffAbsenceDay::CODES))],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $key = ['tenant_id' => $tenantId, 'user_id' => (int) $data['user_id'], 'date' => $data['date']];

        if (empty($data['code'])) {
            StaffAbsenceDay::query()->where($key)->delete();

            return response()->json(['success' => true]);
        }

        StaffAbsenceDay::query()->updateOrCreate($key, [
            'code'    => $data['code'],
            'minutes' => $data['minutes'] ?? null,
            'note'    => $data['note'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /** Manueel een check-in toevoegen (bv. vergeten in te checken). */
    public function storeCheckin(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();

        $data = $request->validate([
            'user_id'        => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'checked_in_at'  => ['required', 'date'],
            'checked_out_at' => ['required', 'date', 'after:checked_in_at'],
        ]);

        $admin = $request->attributes->get('backoffice_user');

        $checkin = StaffCheckin::query()->create([
            'tenant_id'      => $tenantId,
            'user_id'        => (int) $data['user_id'],
            'checked_in_at'  => Carbon::parse($data['checked_in_at']),
            'checked_out_at' => Carbon::parse($data['checked_out_at']),
            'checked_in_by'  => $admin?->id,
            'checked_out_by' => $admin?->id,
        ]);

        return response()->json(['id' => $checkin->id], 201);
    }

    /** Maand afvinken als ingevoerd (of terug openzetten). */
    public function submit(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();

        $data = $request->validate([
            'user_id'   => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'month'     => ['required', 'date_format:Y-m'],
            'submitted' => ['required', 'boolean'],
        ]);

        $key = ['tenant_id' => $tenantId, 'user_id' => (int) $data['user_id'], 'period' => $data['month']];

        if (! $data['submitted']) {
            StaffPayrollMonth::query()->where($key)->delete();

            return response()->json(['success' => true]);
        }

        [$start, $end] = $this->periodRange($data['month']);
        $days = $this->buildDays(
            $start, $end,
            StaffCheckin::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $key['user_id'])
                ->where('checked_in_at', '<=', $end)
                ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $start))
                ->get(),
            StaffAbsenceDay::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $key['user_id'])
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(),
            collect(), collect(), [],
        );

        $issues = collect($days)->filter(fn ($d) => $d['issue'])->count();
        if ($issues > 0) {
            return response()->json([
                'message' => 'Er zijn nog ' . $issues . ' dag(en) te controleren (niet uitgecheckt).',
            ], 422);
        }

        $admin = $request->attributes->get('backoffice_user');

        StaffPayrollMonth::query()->updateOrCreate($key, [
            'submitted_at'   => now(),
            'submitted_by'   => $admin?->id,
            'worked_minutes' => (int) collect($days)->sum('worked_minutes'),
            'snapshot_hash'  => $this->hashDays($days),
        ]);

        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------------

    private function buildDays(
        Carbon $start,
        Carbon $end,
        Collection $sessions,
        Collection $absences,
        Collection $roster,
        Collection $leave,
        array $holidays,
    ): array {
        $days = [];
        $today = now()->toDateString();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $days[$date] = [
                'date'           => $date,
                'day'            => $d->day,
                'weekday'        => $d->dayOfWeekIso, // 1 = maandag
                'sessions'       => [],
                'worked_minutes' => 0,
                'absence'        => null,
                'roster'         => [],
                'leave'          => false,
                'holiday'        => $holidays[$date] ?? null,
                'issue'          => null,
            ];
        }

        foreach ($sessions as $s) {
            $in = $s->checked_in_at->copy()->startOfMinute();

            if ($s->checked_out_at === null) {
                $date = $in->toDateString();
                if (! isset($days[$date])) {
                    continue;
                }
                $days[$date]['sessions'][] = $this->mapSession($s, $in, null, 0);
                if ($date !== $today) {
                    $days[$date]['issue'] = 'Niet uitgecheckt';
                }
                continue;
            }

            $out = $s->checked_out_at->copy()->startOfMinute();

            // Sessies over middernacht splitsen per kalenderdag.
            $cursor = $in->copy();
            while ($cursor->lt($out)) {
                $dayEnd = $cursor->copy()->addDay()->startOfDay();
                $sliceEnd = $out->lt($dayEnd) ? $out->copy() : $dayEnd;
                $date = $cursor->toDateString();

                if (isset($days[$date])) {
                    $minutes = intdiv($sliceEnd->getTimestamp() - $cursor->getTimestamp(), 60);
                    $days[$date]['sessions'][] = $this->mapSession($s, $cursor, $sliceEnd, $minutes);
                    $days[$date]['worked_minutes'] += $minutes;
                }

                $cursor = $sliceEnd;
            }
        }

        foreach ($absences as $a) {
            $date = $a->date->toDateString();
            if (isset($days[$date])) {
                $days[$date]['absence'] = [
                    'code'    => $a->code,
                    'minutes' => $a->minutes,
                    'note'    => $a->note,
                ];
            }
        }

        foreach ($roster as $r) {
            $date = Carbon::parse($r->date)->toDateString();
            if (isset($days[$date])) {
                $from = substr((string) $r->starts_at, 0, 5);
                $to = substr((string) $r->ends_at, 0, 5);
                $minutes = $this->hmToMinutes($to) - $this->hmToMinutes($from);
                if ($minutes <= 0) {
                    $minutes += 1440;
                }
                $days[$date]['roster'][] = ['starts_at' => $from, 'ends_at' => $to, 'minutes' => $minutes];
            }
        }

        foreach ($leave as $l) {
            for ($d = Carbon::parse($l->start_date); $d->lte(Carbon::parse($l->end_date)); $d->addDay()) {
                if (isset($days[$d->toDateString()])) {
                    $days[$d->toDateString()]['leave'] = true;
                }
            }
        }

        return $days;
    }

    private function mapSession(StaffCheckin $s, Carbon $from, ?Carbon $to, int $minutes): array
    {
        return [
            'id'             => $s->id,
            'user_id'        => $s->user_id,
            'rfid_uid'       => $s->rfid_uid,
            'in'             => $from->format('H:i'),
            'out'            => $to?->format('H:i'),
            'minutes'        => $minutes,
            'open'           => $s->checked_out_at === null,
            'checked_in_at'  => $s->checked_in_at?->format('Y-m-d\TH:i'),
            'checked_out_at' => $s->checked_out_at?->format('Y-m-d\TH:i'),
        ];
    }

    private function summary(User $u, array $days, ?StaffPayrollMonth $month): array
    {
        $collection = collect($days);
        $worked = (int) $collection->sum('worked_minutes');
        $issues = $collection->filter(fn ($d) => $d['issue'])->count();
        $absenceDays = $collection->filter(fn ($d) => $d['absence'])->count();

        if ($month?->submitted_at) {
            $status = $month->snapshot_hash === $this->hashDays($days) ? 'submitted' : 'changed';
        } elseif ($issues > 0) {
            $status = 'issues';
        } elseif ($worked > 0 || $absenceDays > 0) {
            $status = 'ready';
        } else {
            $status = 'empty';
        }

        return [
            'id'             => $u->id,
            'name'           => $u->name,
            'is_active'      => (bool) $u->is_active,
            'worked_minutes' => $worked,
            'worked_days'    => $collection->filter(fn ($d) => $d['worked_minutes'] > 0)->count(),
            'absence_days'   => $absenceDays,
            'issues'         => $issues,
            'status'         => $status,
            'submitted_at'   => $month?->submitted_at?->format('d/m/Y H:i'),
        ];
    }

    /** Hash over wat er in Liantis ingegeven wordt: minuten + afwezigheid per dag. */
    private function hashDays(array $days): string
    {
        $payload = collect($days)
            ->map(fn ($d) => [$d['date'], $d['worked_minutes'], $d['absence']['code'] ?? null, $d['absence']['minutes'] ?? null])
            ->values()
            ->all();

        return hash('sha256', json_encode($payload));
    }

    private function rosterFor(int $tenantId, int $userId, Carbon $start, Carbon $end): Collection
    {
        return RosterShift::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get(['id', 'date', 'starts_at', 'ends_at']);
    }

    private function leaveFor(int $tenantId, int $userId, Carbon $start, Carbon $end): Collection
    {
        return LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);
    }

    /** Eerste helft van de maand → vorige maand (dan geef je die in). */
    private function defaultPeriod(): string
    {
        $now = now();

        return ($now->day <= 15 ? $now->copy()->subMonthNoOverflow() : $now)->format('Y-m');
    }

    private function periodRange(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    private function monthLabel(Carbon $start): string
    {
        $names = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

        return ucfirst($names[$start->month - 1]) . ' ' . $start->year;
    }

    private function hmToMinutes(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [0, 0]);

        return $h * 60 + $m;
    }

    /** Wettelijke feestdagen België. */
    private function belgianHolidays(int $year): array
    {
        // Pasen (anoniem Gregoriaans algoritme) — geen calendar-extensie nodig.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        $easter = Carbon::create($year, $month, $day);

        return [
            "$year-01-01" => 'Nieuwjaar',
            $easter->copy()->addDay()->toDateString() => 'Paasmaandag',
            "$year-05-01" => 'Dag van de Arbeid',
            $easter->copy()->addDays(39)->toDateString() => 'O.L.H. Hemelvaart',
            $easter->copy()->addDays(50)->toDateString() => 'Pinkstermaandag',
            "$year-07-21" => 'Nationale feestdag',
            "$year-08-15" => 'O.L.V. Hemelvaart',
            "$year-11-01" => 'Allerheiligen',
            "$year-11-11" => 'Wapenstilstand',
            "$year-12-25" => 'Kerstmis',
        ];
    }
}

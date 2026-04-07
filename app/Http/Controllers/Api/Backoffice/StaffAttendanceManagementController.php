<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Models\StaffCheckin;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StaffAttendanceManagementController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = (int) $currentTenant->id();
        $validated = $request->validate([
            'staff_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['all', 'open', 'closed'])],
        ]);

        $start = ! empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = ! empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            return response()->json([
                'message' => 'De einddatum mag niet voor de startdatum liggen.',
                'errors' => [
                    'date_to' => ['De einddatum mag niet voor de startdatum liggen.'],
                ],
            ], 422);
        }

        $status = $validated['status'] ?? 'all';
        $staffId = isset($validated['staff_id']) ? (int) $validated['staff_id'] : null;

        if ($staffId) {
            $staffExists = User::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $staffId)
                ->exists();

            if (! $staffExists) {
                return response()->json([
                    'message' => 'Ongeldige medewerker geselecteerd.',
                    'errors' => [
                        'staff_id' => ['Ongeldige medewerker geselecteerd.'],
                    ],
                ], 422);
            }
        }

        $staffUsers = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $badgePreviewMap = $this->buildBadgePreviewMap($tenantId, $staffUsers->pluck('id')->all());

        $staff = $staffUsers
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'is_active' => (bool) $user->is_active,
                'badge_preview_url' => $badgePreviewMap[$user->id] ?? null,
            ])
            ->values();

        $sessions = StaffCheckin::query()
            ->with(['staff:id,name', 'checkedInBy:id,name', 'checkedOutBy:id,name'])
            ->where('tenant_id', $tenantId)
            ->when($staffId, fn ($query) => $query->where('user_id', $staffId))
            ->when($status === 'open', fn ($query) => $query->whereNull('checked_out_at'))
            ->when($status === 'closed', fn ($query) => $query->whereNotNull('checked_out_at'))
            ->where('checked_in_at', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('checked_out_at')
                    ->orWhere('checked_out_at', '>=', $start);
            })
            ->orderByDesc('checked_in_at')
            ->get();

        $openSessions = StaffCheckin::query()
            ->with(['staff:id,name', 'checkedInBy:id,name'])
            ->where('tenant_id', $tenantId)
            ->when($staffId, fn ($query) => $query->where('user_id', $staffId))
            ->whereNull('checked_out_at')
            ->orderBy('checked_in_at')
            ->get();

        $staffSummaries = $this->buildStaffSummaries($sessions, $start, $end, $badgePreviewMap);
        $selectedStaffDays = $staffId ? $this->buildDaySummaries($sessions->where('user_id', $staffId)->values(), $start, $end) : [];

        return response()->json([
            'filters' => [
                'staff_id' => $staffId,
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'status' => $status,
            ],
            'staff' => $staff,
            'stats' => [
                'open_sessions' => $openSessions->count(),
                'session_count' => $sessions->count(),
                'worked_minutes' => (int) $sessions->sum(fn (StaffCheckin $session) => $this->minutesWithinRange($session, $start, $end)),
                'worked_time_label' => $this->formatMinutes((int) $sessions->sum(fn (StaffCheckin $session) => $this->minutesWithinRange($session, $start, $end))),
                'staff_with_hours' => $staffSummaries->count(),
            ],
            'open_sessions' => $openSessions->map(fn (StaffCheckin $session) => $this->mapSession($session, $start, $end, $badgePreviewMap))->values(),
            'sessions' => $sessions->map(fn (StaffCheckin $session) => $this->mapSession($session, $start, $end, $badgePreviewMap))->values(),
            'staff_summaries' => $staffSummaries->values(),
            'selected_staff_days' => array_values($selectedStaffDays),
        ]);
    }

    public function update(Request $request, CurrentTenant $currentTenant, StaffCheckin $staffAttendance): JsonResponse
    {
        abort_unless((int) $staffAttendance->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $currentTenant->id())),
            ],
            'rfid_uid' => ['nullable', 'string', 'max:100'],
            'checked_in_at' => ['required', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after_or_equal:checked_in_at'],
        ]);

        $staffAttendance->fill([
            'user_id' => (int) $data['user_id'],
            'rfid_uid' => $this->nullableString($data['rfid_uid'] ?? null),
            'checked_in_at' => Carbon::parse($data['checked_in_at']),
            'checked_out_at' => ! empty($data['checked_out_at']) ? Carbon::parse($data['checked_out_at']) : null,
        ])->save();

        return response()->json([
            'item' => $this->mapSession(
                $staffAttendance->fresh(['staff:id,name', 'checkedInBy:id,name', 'checkedOutBy:id,name']),
                $staffAttendance->checked_in_at->copy()->startOfDay(),
                ($staffAttendance->checked_out_at ?? now())->copy()->endOfDay(),
            ),
        ]);
    }

    public function destroy(CurrentTenant $currentTenant, StaffCheckin $staffAttendance): JsonResponse
    {
        abort_unless((int) $staffAttendance->tenant_id === (int) $currentTenant->id(), 404);

        $staffAttendance->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function buildStaffSummaries(Collection $sessions, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null, array $badgePreviewMap = []): Collection
    {
        return $sessions
            ->groupBy('user_id')
            ->map(function (Collection $groupedSessions) use ($rangeStart, $rangeEnd) {
                /** @var StaffCheckin|null $first */
                $first = $groupedSessions->first();
                $workedMinutes = (int) $groupedSessions->sum(fn (StaffCheckin $session) => $this->minutesWithinRange($session, $rangeStart, $rangeEnd));

                $workedDays = collect($this->buildDaySummaries($groupedSessions, $rangeStart, $rangeEnd))
                    ->filter(fn (array $day) => ($day['total_minutes'] ?? 0) > 0)
                    ->count();

                return [
                    'user_id' => $first?->user_id,
                    'user_name' => $first?->staff?->name ?? 'Onbekend',
                    'worked_days' => $workedDays,
                    'session_count' => $groupedSessions->count(),
                    'worked_minutes' => $workedMinutes,
                    'worked_time_label' => $this->formatMinutes($workedMinutes),
                    'first_check_in_label' => $groupedSessions->min('checked_in_at')?->format('d/m/Y H:i'),
                    'last_check_out_label' => $groupedSessions
                        ->map(fn (StaffCheckin $session) => $session->checked_out_at ?? now())
                        ->sortByDesc(fn (CarbonInterface $date) => $date->timestamp)
                        ->first()?->format('d/m/Y H:i'),
                    'is_active' => $groupedSessions->contains(fn (StaffCheckin $session) => $session->checked_out_at === null),
                    'badge_preview_url' => $badgePreviewMap[$first?->user_id] ?? null,
                ];
            })
            ->sortBy('user_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function buildDaySummaries(Collection $sessions, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null): array
    {
        $days = [];

        foreach ($sessions as $session) {
            foreach ($this->splitSessionByDay($session, $rangeStart, $rangeEnd) as $slice) {
                $date = $slice['date'];
                if (! isset($days[$date])) {
                    $days[$date] = [
                        'date' => $date,
                        'date_label' => Carbon::parse($date)->format('d/m/Y'),
                        'first_check_in_label' => null,
                        'last_check_out_label' => null,
                        'total_minutes' => 0,
                        'total_time_label' => '0 min',
                        'sessions' => [],
                    ];
                }

                $days[$date]['total_minutes'] += $slice['minutes'];
                $days[$date]['sessions'][] = [
                    'id' => $session->id,
                    'checked_in_at_label' => $slice['start']->format('H:i'),
                    'checked_out_at_label' => $slice['end']->format('H:i'),
                    'duration_label' => $this->formatMinutes($slice['minutes']),
                    'is_active' => $session->checked_out_at === null,
                ];

                $startLabel = $slice['start']->format('H:i');
                $endLabel = $slice['end']->format('H:i');

                if ($days[$date]['first_check_in_label'] === null || strcmp($startLabel, $days[$date]['first_check_in_label']) < 0) {
                    $days[$date]['first_check_in_label'] = $startLabel;
                }

                if ($days[$date]['last_check_out_label'] === null || strcmp($endLabel, $days[$date]['last_check_out_label']) > 0) {
                    $days[$date]['last_check_out_label'] = $endLabel;
                }
            }
        }

        krsort($days);

        foreach ($days as &$day) {
            $day['total_time_label'] = $this->formatMinutes((int) $day['total_minutes']);
            usort($day['sessions'], fn (array $a, array $b) => strcmp($a['checked_in_at_label'], $b['checked_in_at_label']));
        }

        return $days;
    }

    private function splitSessionByDay(StaffCheckin $session, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null): array
    {
        $start = $session->checked_in_at?->copy();
        $end = ($session->checked_out_at ?? now())?->copy();

        if ($rangeStart && $start && $start->lt($rangeStart)) {
            $start = $rangeStart->copy();
        }

        if ($rangeEnd && $end && $end->gt($rangeEnd)) {
            $end = $rangeEnd->copy();
        }

        if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
            return [];
        }

        $segments = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $dayEnd = $cursor->copy()->endOfDay();
            $segmentEnd = $end->lt($dayEnd) ? $end->copy() : $dayEnd->copy();
            $minutes = max(1, $cursor->diffInMinutes($segmentEnd));

            $segments[] = [
                'date' => $cursor->toDateString(),
                'start' => $cursor->copy(),
                'end' => $segmentEnd->copy(),
                'minutes' => $minutes,
            ];

            $cursor = $segmentEnd->copy()->addSecond();
        }

        return $segments;
    }

    private function mapSession(StaffCheckin $session, ?Carbon $start = null, ?Carbon $end = null, array $badgePreviewMap = []): array
    {
        $workedMinutes = $this->minutesWithinRange($session, $start, $end);

        return [
            'id' => $session->id,
            'user_id' => $session->user_id,
            'user_name' => $session->staff?->name ?? 'Onbekend',
            'rfid_uid' => $session->rfid_uid,
            'checked_in_at' => $session->checked_in_at?->format('Y-m-d\TH:i'),
            'checked_out_at' => $session->checked_out_at?->format('Y-m-d\TH:i'),
            'checked_in_at_full_label' => $session->checked_in_at?->format('d/m/Y H:i'),
            'checked_out_at_full_label' => $session->checked_out_at?->format('d/m/Y H:i') ?? 'Nog open',
            'duration_label' => $this->formatMinutes($workedMinutes),
            'worked_minutes' => $workedMinutes,
            'is_active' => $session->checked_out_at === null,
            'checked_in_by_name' => $session->checkedInBy?->name,
            'checked_out_by_name' => $session->checkedOutBy?->name,
            'badge_preview_url' => $badgePreviewMap[$session->user_id] ?? null,
        ];
    }

    private function buildBadgePreviewMap(int $tenantId, array $staffIds): array
    {
        if (empty($staffIds)) {
            return [];
        }

        return PhysicalCard::query()
            ->where('tenant_id', $tenantId)
            ->where('card_type', PhysicalCard::TYPE_STAFF)
            ->whereIn('holder_id', $staffIds)
            ->orderByDesc('updated_at')
            ->get(['holder_id', 'render_image_path', 'updated_at'])
            ->groupBy('holder_id')
            ->map(function (Collection $cards) {
                $card = $cards->first(fn (PhysicalCard $item) => filled($item->render_image_path) && Storage::disk('public')->exists($item->render_image_path))
                    ?? $cards->first();

                if (! $card || blank($card->render_image_path) || ! Storage::disk('public')->exists($card->render_image_path)) {
                    return null;
                }

                return Storage::disk('public')->url($card->render_image_path) . '?v=' . urlencode((string) optional($card->updated_at)->timestamp);
            })
            ->filter()
            ->toArray();
    }

    private function minutesWithinRange(StaffCheckin $session, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null): int
    {
        $start = $session->checked_in_at?->copy();
        $end = ($session->checked_out_at ?? now())?->copy();

        if ($rangeStart && $start && $start->lt($rangeStart)) {
            $start = $rangeStart->copy();
        }

        if ($rangeEnd && $end && $end->gt($rangeEnd)) {
            $end = $rangeEnd->copy();
        }

        if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
            return 0;
        }

        if ($rangeStart && $start->lt($rangeStart)) {
            $start = $rangeStart->copy();
        }

        if ($rangeEnd && $end->gt($rangeEnd)) {
            $end = $rangeEnd->copy();
        }

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(1, $start->diffInMinutes($end));
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%du %02d min', $hours, $remainingMinutes);
        }

        return sprintf('%d min', $remainingMinutes);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

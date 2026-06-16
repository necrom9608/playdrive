<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\RosterShift;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verlofaanvragen vanuit de staff-app. De medewerker ziet en beheert enkel de
 * eigen aanvragen; goedkeuren/afwijzen gebeurt in de backoffice.
 */
class LeaveController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user     = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();

        $requests = LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $requests->map(fn (LeaveRequest $r) => $this->map($r, $tenantId))->values(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user     = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);

        $leave = LeaveRequest::query()->create([
            'tenant_id'  => $tenantId,
            'user_id'    => $user->id,
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'reason'     => $data['reason'] ?? null,
            'status'     => LeaveRequest::STATUS_PENDING,
        ]);

        return response()->json(['data' => $this->map($leave, $tenantId)], 201);
    }

    /** Medewerker trekt een aanvraag in (pending of goedgekeurd). */
    public function destroy(Request $request, CurrentTenant $currentTenant, LeaveRequest $leave): JsonResponse
    {
        $user     = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();

        abort_unless((int) $leave->tenant_id === $tenantId && (int) $leave->user_id === (int) $user->id, 404);

        abort_unless(
            in_array($leave->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true),
            422,
            'Deze aanvraag kan niet meer ingetrokken worden.'
        );

        $leave->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        return response()->json(['data' => $this->map($leave->fresh(), $tenantId)]);
    }

    private function map(LeaveRequest $r, int $tenantId): array
    {
        $start = $r->start_date?->toDateString();
        $end   = $r->end_date?->toDateString();

        return [
            'id'            => $r->id,
            'start_date'    => $start,
            'end_date'      => $end,
            'period_label'  => $this->periodLabel($r),
            'days'          => ($r->start_date && $r->end_date)
                ? $r->start_date->diffInDays($r->end_date) + 1
                : 1,
            'reason'        => $r->reason,
            'status'        => $r->status,
            'status_label'  => $this->statusLabel($r->status),
            'review_note'   => $r->review_note,
            'conflict_count' => $this->conflictCount($tenantId, (int) $r->user_id, $start, $end),
            'can_cancel'    => in_array($r->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true),
        ];
    }

    private function conflictCount(int $tenantId, int $userId, ?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        return RosterShift::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

    private function periodLabel(LeaveRequest $r): string
    {
        if (! $r->start_date || ! $r->end_date) {
            return '';
        }

        if ($r->start_date->isSameDay($r->end_date)) {
            return ucfirst($r->start_date->locale('nl_BE')->isoFormat('ddd D MMM YYYY'));
        }

        $sameYear = $r->start_date->year === $r->end_date->year;

        return ucfirst($r->start_date->locale('nl_BE')->isoFormat($sameYear ? 'ddd D MMM' : 'ddd D MMM YYYY'))
            . ' – ' . ucfirst($r->end_date->locale('nl_BE')->isoFormat('ddd D MMM YYYY'));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            LeaveRequest::STATUS_PENDING   => 'In afwachting',
            LeaveRequest::STATUS_APPROVED  => 'Goedgekeurd',
            LeaveRequest::STATUS_REJECTED  => 'Afgewezen',
            LeaveRequest::STATUS_CANCELLED => 'Ingetrokken',
            default                        => ucfirst($status),
        };
    }
}

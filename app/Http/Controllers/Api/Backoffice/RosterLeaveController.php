<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\RosterShift;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Beheer van verlofaanvragen in de backoffice. Toont per aanvraag of die
 * samenvalt met shiften waarin de medewerker al is ingepland (conflict), zodat
 * de beoordelaar visueel ziet wanneer goedkeuren niet past.
 */
class RosterLeaveController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();

        $query = LeaveRequest::query()
            ->with('staff:id,name')
            ->where('tenant_id', $tenantId);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        // Sorteer: open aanvragen eerst, dan op begindatum.
        $requests = $query->get()
            ->sortBy([
                fn (LeaveRequest $r) => $r->status === LeaveRequest::STATUS_PENDING ? 0 : 1,
                fn (LeaveRequest $r) => $r->start_date?->toDateString(),
            ])
            ->values();

        $pendingCount = $requests->where('status', LeaveRequest::STATUS_PENDING)->count();

        return response()->json([
            'data'          => $requests->map(fn (LeaveRequest $r) => $this->map($r, $tenantId))->all(),
            'pending_count' => $pendingCount,
        ]);
    }

    public function approve(Request $request, CurrentTenant $currentTenant, LeaveRequest $leave): JsonResponse
    {
        return $this->review($request, $currentTenant, $leave, LeaveRequest::STATUS_APPROVED);
    }

    public function reject(Request $request, CurrentTenant $currentTenant, LeaveRequest $leave): JsonResponse
    {
        return $this->review($request, $currentTenant, $leave, LeaveRequest::STATUS_REJECTED);
    }

    private function review(Request $request, CurrentTenant $currentTenant, LeaveRequest $leave, string $status): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = (int) $currentTenant->id();
        abort_unless((int) $leave->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->attributes->get('backoffice_user');

        $leave->update([
            'status'      => $status,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        return response()->json(['data' => $this->map($leave->fresh()->load('staff:id,name'), $tenantId)]);
    }

    private function map(LeaveRequest $r, int $tenantId): array
    {
        $start = $r->start_date?->toDateString();
        $end   = $r->end_date?->toDateString();

        $conflicts = $this->conflicts($tenantId, (int) $r->user_id, $start, $end);

        return [
            'id'            => $r->id,
            'user_id'       => $r->user_id,
            'staff_name'    => $r->staff?->name ?? ('#' . $r->user_id),
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
            'reviewed_at_label' => $r->reviewed_at?->locale('nl_BE')->isoFormat('D MMM YYYY HH:mm'),
            'conflicts'     => $conflicts,
            'conflict_count' => count($conflicts),
        ];
    }

    /** Shiften in de periode waarin deze medewerker is toegewezen. */
    private function conflicts(int $tenantId, int $userId, ?string $start, ?string $end): array
    {
        if (! $start || ! $end) {
            return [];
        }

        return RosterShift::query()
            ->with('role:id,name')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (RosterShift $s) => [
                'id'        => $s->id,
                'date'      => $s->date?->toDateString(),
                'date_label' => $s->date
                    ? ucfirst($s->date->locale('nl_BE')->isoFormat('ddd D MMM'))
                    : null,
                'time_label' => substr((string) $s->starts_at, 0, 5) . '–' . substr((string) $s->ends_at, 0, 5),
                'role_name' => $s->role?->name,
            ])
            ->all();
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

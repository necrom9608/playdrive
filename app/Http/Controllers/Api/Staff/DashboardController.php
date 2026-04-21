<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Registration;
use App\Models\StaffCheckin;
use App\Models\Task;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $request->attributes->get('staff_user');
        $tenantId = (int) $currentTenant->id();
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())->startOfDay()
            : now()->startOfDay();
        $today = now()->startOfDay();

        $activeSession = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        $sessionsForDay = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where(function ($query) use ($selectedDate) {
                $query->whereDate('checked_in_at', $selectedDate)
                    ->orWhereDate('checked_out_at', $selectedDate);
            })
            ->latest('checked_in_at')
            ->get();

        $workedMinutesForDay = $sessionsForDay->sum(function (StaffCheckin $session) {
            $end = $session->checked_out_at ?? now();

            return max(0, $session->checked_in_at?->diffInMinutes($end) ?? 0);
        });

        $registrations = Registration::query()
            ->with(['cateringOption:id,name,emoji'])
            ->where('tenant_id', $tenantId)
            ->whereDate('event_date', $selectedDate)
            ->get();

        $registrationStatuses = collect([
            Registration::STATUS_NEW,
            Registration::STATUS_PENDING,
            Registration::STATUS_CONFIRMED,
            Registration::STATUS_CHECKED_IN,
            Registration::STATUS_CHECKED_OUT,
            Registration::STATUS_PAID,
            Registration::STATUS_CANCELLED,
            Registration::STATUS_NO_SHOW,
        ])->map(function (string $status) use ($registrations) {
            $items = $registrations->where('status', $status);

            return [
                'key' => $status,
                'label' => Registration::statusOptions()[$status] ?? ucfirst($status),
                'count' => $items->count(),
                'participants' => (int) $items->sum(fn (Registration $registration) => (int) $registration->total_participants),
            ];
        })->values();

        $cateringSummary = $registrations
            ->filter(fn (Registration $registration) => $registration->cateringOption && strtolower((string) $registration->cateringOption->name) !== 'geen')
            ->groupBy('catering_option_id')
            ->map(function ($items) {
                /** @var Registration $first */
                $first = $items->first();

                return [
                    'key' => (string) ($first->catering_option_id ?? $first->cateringOption?->name ?? uniqid('catering_', true)),
                    'label' => $first->cateringOption?->name ?? 'Catering',
                    'emoji' => $first->cateringOption?->emoji ?: '🍽️',
                    'count' => $items->count(),
                    'participants' => (int) $items->sum(fn (Registration $registration) => (int) $registration->total_participants),
                ];
            })
            ->values();

        $taskBaseQuery = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Task::STATUS_OPEN)
            ->where(function ($query) use ($user) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereNull('assigned_user_id');
            });

        $tasksForDay = (clone $taskBaseQuery)
            ->with(['assignedUser:id,name'])
            ->where(function ($query) use ($selectedDate) {
                $query->whereDate('due_date', $selectedDate)
                    ->orWhereDate('start_date', $selectedDate)
                    ->orWhere(function ($range) use ($selectedDate) {
                        $range->whereDate('start_date', '<=', $selectedDate)
                            ->whereDate('end_date', '>=', $selectedDate);
                    });
            })
            ->orderByRaw('coalesce(due_date, start_date, created_at) asc')
            ->limit(8)
            ->get();

        $openTaskCount = (clone $taskBaseQuery)->count();
        $overdueTaskCount = (clone $taskBaseQuery)
            ->whereDate('due_date', '<', $selectedDate)
            ->count();

        $revenueTotal = null;

        if ((bool) $user->is_admin) {
            $revenueTotal = (float) Order::query()
                ->where('tenant_id', $tenantId)
                ->where('status', Order::STATUS_PAID)
                ->whereDate('paid_at', $selectedDate)
                ->sum('total_incl_vat');
        }

        return response()->json([
            'data' => [
                'selected_date' => $selectedDate->toDateString(),
                'selected_date_label' => ucfirst($selectedDate->locale('nl_BE')->isoFormat('dddd D MMMM YYYY')),
                'is_today' => $selectedDate->isSameDay($today),
                'attendance' => [
                    'is_checked_in' => $activeSession !== null,
                    'checked_in_at' => $activeSession?->checked_in_at?->toIso8601String(),
                    'checked_in_at_label' => $activeSession?->checked_in_at?->format('H:i'),
                    'current_duration_minutes' => $activeSession?->checked_in_at?->diffInMinutes(now()) ?? 0,
                    'current_duration_label' => $activeSession?->checked_in_at ? $this->formatMinutes((int) $activeSession->checked_in_at->diffInMinutes(now())) : '00:00',
                    'worked_minutes_for_day' => $workedMinutesForDay,
                    'worked_time_for_day_label' => $this->formatMinutes($workedMinutesForDay),
                ],
                'reservations' => [
                    'total' => $registrations->count(),
                    'participants' => (int) $registrations->sum(fn (Registration $registration) => (int) $registration->total_participants),
                    'statuses' => $registrationStatuses,
                ],
                'catering' => [
                    'total' => $cateringSummary->sum('count'),
                    'items' => $cateringSummary,
                ],
                'tasks' => [
                    'open_count' => $openTaskCount,
                    'overdue_count' => $overdueTaskCount,
                    'items' => $tasksForDay->map(fn (Task $task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'assigned_user_name' => $task->assignedUser?->name,
                        'assigned_to_me' => (int) $task->assigned_user_id === (int) $user->id,
                        'due_date' => $task->due_date?->toDateString(),
                        'due_date_label' => $task->due_date?->format('d/m/Y'),
                        'start_date_label' => $task->start_date?->format('d/m/Y'),
                        'is_overdue' => $task->due_date?->lt($selectedDate) ?? false,
                    ])->values(),
                ],
                'sessions_for_day' => $sessionsForDay->take(8)->map(fn (StaffCheckin $session) => [
                    'id' => $session->id,
                    'checked_in_at_label' => $session->checked_in_at?->format('H:i'),
                    'checked_out_at_label' => $session->checked_out_at?->format('H:i'),
                    'duration_label' => $this->formatMinutes((int) max(0, $session->checked_in_at?->diffInMinutes($session->checked_out_at ?? now()) ?? 0)),
                    'is_active' => $session->checked_out_at === null,
                ])->values(),
                'revenue' => [
                    'visible' => (bool) $user->is_admin,
                    'total' => $revenueTotal,
                    'label' => $revenueTotal !== null ? number_format($revenueTotal, 2, ',', '.') : null,
                ],
            ],
        ]);
    }

    protected function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}

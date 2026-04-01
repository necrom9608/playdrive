<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
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
        $today = Carbon::today();

        $activeSession = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        $todaySessions = StaffCheckin::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where(function ($query) use ($today) {
                $query->whereDate('checked_in_at', $today)
                    ->orWhereDate('checked_out_at', $today);
            })
            ->latest('checked_in_at')
            ->get();

        $workedMinutes = $todaySessions->sum(function (StaffCheckin $session) {
            $end = $session->checked_out_at ?? now();
            return max(0, $session->checked_in_at?->diffInMinutes($end) ?? 0);
        });

        $taskQuery = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Task::STATUS_OPEN)
            ->where(function ($query) use ($user) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereNull('assigned_user_id');
            });

        $tasksToday = (clone $taskQuery)
            ->where(function ($query) use ($today) {
                $query->whereDate('due_date', $today)
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->count();

        $myTasks = (clone $taskQuery)->count();

        $upcomingTasks = (clone $taskQuery)
            ->orderByRaw('coalesce(due_date, start_date, created_at) asc')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'assigned_to_me' => (int) $task->assigned_user_id === (int) $user->id,
                'due_label' => optional($task->due_date ?? $task->start_date)->format('d/m/Y'),
            ])
            ->values();

        $todayRegistrations = Registration::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('event_date', $today)
            ->count();

        return response()->json([
            'data' => [
                'attendance' => [
                    'is_checked_in' => $activeSession !== null,
                    'checked_in_at' => $activeSession?->checked_in_at?->toIso8601String(),
                    'checked_in_at_label' => $activeSession?->checked_in_at?->format('H:i'),
                    'worked_minutes_today' => $workedMinutes,
                    'worked_time_today_label' => sprintf('%02d:%02d', intdiv($workedMinutes, 60), $workedMinutes % 60),
                ],
                'stats' => [
                    'registrations_today' => $todayRegistrations,
                    'my_open_tasks' => $myTasks,
                    'tasks_today' => $tasksToday,
                    'checked_in_staff_now' => StaffCheckin::query()->where('tenant_id', $tenantId)->whereNull('checked_out_at')->count(),
                ],
                'tasks' => $upcomingTasks,
                'sessions_today' => $todaySessions->take(5)->map(fn (StaffCheckin $session) => [
                    'id' => $session->id,
                    'checked_in_at_label' => $session->checked_in_at?->format('H:i'),
                    'checked_out_at_label' => $session->checked_out_at?->format('H:i'),
                    'duration_label' => sprintf('%02d:%02d', intdiv(max(0, $session->checked_in_at?->diffInMinutes($session->checked_out_at ?? now()) ?? 0), 60), max(0, $session->checked_in_at?->diffInMinutes($session->checked_out_at ?? now()) ?? 0) % 60),
                    'is_active' => $session->checked_out_at === null,
                ])->values(),
            ],
        ]);
    }
}

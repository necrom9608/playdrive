<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Api\Frontdesk\AgendaController as FrontdeskAgendaController;
use App\Models\Registration;
use App\Models\Task;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends FrontdeskAgendaController
{
    public function __invoke(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $request->attributes->get('staff_user');
        $view = $request->string('view')->toString() ?: 'day';
        $view = in_array($view, ['day', 'week', 'month'], true) ? $view : 'day';

        $anchorDate = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())->startOfDay()
            : now()->startOfDay();

        [$rangeStart, $rangeEnd] = $this->resolveRange($view, $anchorDate);

        $registrations = Registration::query()
            ->with(['eventType:id,name,code,emoji', 'stayOption:id,name,code,emoji,duration_minutes', 'cateringOption:id,name,code,emoji'])
            ->where('tenant_id', $currentTenant->id())
            ->whereBetween('event_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->orderBy('id')
            ->get()
            ->map(fn (Registration $registration) => $this->transformRegistration($registration))
            ->values();

        $tasks = Task::query()
            ->with(['assignedUser:id,name', 'scheduler:id,name'])
            ->where('tenant_id', $currentTenant->id())
            ->where('status', '!=', Task::STATUS_CANCELLED)
            ->where(function ($query) use ($user) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereNull('assigned_user_id');
            })
            ->get();

        $taskOccurrences = $this->buildTaskOccurrences($tasks, $rangeStart, $rangeEnd);
        $dayItems = $registrations->concat($taskOccurrences)
            ->sortBy(fn (array $item) => ($item['event_date'] ?? '9999-12-31') . ' ' . ($item['sort_time'] ?? '23:59'))
            ->values();

        $selectedDayItems = $dayItems->where('event_date', $anchorDate->toDateString())->values();
        $days = collect();
        $cursor = $rangeStart->copy();

        while ($cursor->lte($rangeEnd)) {
            $dateKey = $cursor->toDateString();
            $items = $dayItems->where('event_date', $dateKey)->values();
            $registrationsForDay = $items->where('item_type', 'registration')->values();
            $tasksForDay = $items->where('item_type', 'task')->values();

            $days->push([
                'date' => $dateKey,
                'day_number' => $cursor->day,
                'weekday_short' => $cursor->locale('nl_BE')->isoFormat('dd'),
                'weekday_label' => ucfirst($cursor->locale('nl_BE')->isoFormat('dddd')),
                'label' => ucfirst($cursor->locale('nl_BE')->isoFormat('ddd D MMM')),
                'is_today' => $cursor->isSameDay(now()),
                'is_selected' => $cursor->isSameDay($anchorDate),
                'is_current_month' => $cursor->month === $anchorDate->month && $cursor->year === $anchorDate->year,
                'totals' => [
                    'reservations' => $registrationsForDay->count(),
                    'tasks' => $tasksForDay->count(),
                    'participants' => (int) $registrationsForDay->sum('total_count'),
                ],
                'status_totals' => $this->buildStatusTotals($registrationsForDay),
                'task_totals' => $this->buildTaskTotals($tasksForDay),
                'catering_totals' => $this->buildCateringTotals($registrationsForDay),
                'registrations' => $view === 'day' ? $items->values() : [],
            ]);

            $cursor->addDay();
        }

        return response()->json([
            'data' => [
                'view' => $view,
                'anchor_date' => $anchorDate->toDateString(),
                'range' => [
                    'start' => $rangeStart->toDateString(),
                    'end' => $rangeEnd->toDateString(),
                    'label' => $this->buildRangeLabel($view, $rangeStart, $rangeEnd, $anchorDate),
                ],
                'summary' => [
                    'reservations' => $registrations->count(),
                    'tasks' => $taskOccurrences->count(),
                    'participants' => (int) $registrations->sum('total_count'),
                ],
                'day_registrations' => $selectedDayItems,
                'days' => $days->values(),
            ],
        ]);
    }
}

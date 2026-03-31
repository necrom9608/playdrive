<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Task;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
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
                    'children' => (int) $registrationsForDay->sum('participants_children'),
                    'adults' => (int) $registrationsForDay->sum('participants_adults'),
                    'supervisors' => (int) $registrationsForDay->sum('participants_supervisors'),
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
                    'children' => (int) $registrations->sum('participants_children'),
                    'adults' => (int) $registrations->sum('participants_adults'),
                    'supervisors' => (int) $registrations->sum('participants_supervisors'),
                    'status_totals' => $this->buildStatusTotals($registrations),
                    'task_totals' => $this->buildTaskTotals($taskOccurrences),
                ],
                'day_registrations' => $selectedDayItems,
                'days' => $days->values(),
            ],
        ]);
    }

    protected function buildTaskOccurrences($tasks, Carbon $rangeStart, Carbon $rangeEnd)
    {
        $occurrences = collect();

        foreach ($tasks as $task) {
            if ($task->task_type === Task::TYPE_SINGLE) {
                $date = $task->due_date?->copy()->startOfDay();

                if ($date && $date->betweenIncluded($rangeStart, $rangeEnd)) {
                    $occurrences->push($this->transformTaskOccurrence($task, $date));
                }

                continue;
            }

            $start = ($task->start_date ?? $task->created_at ?? now())->copy()->startOfDay();
            $end = ($task->end_date ?? $rangeEnd)->copy()->startOfDay();
            $cursor = $rangeStart->copy();

            while ($cursor->lte($rangeEnd)) {
                if ($cursor->lt($start) || $cursor->gt($end)) {
                    $cursor->addDay();
                    continue;
                }

                if ($this->matchesRecurrence($task, $cursor, $start)) {
                    $occurrences->push($this->transformTaskOccurrence($task, $cursor->copy()));
                }

                $cursor->addDay();
            }
        }

        return $occurrences->values();
    }

    protected function matchesRecurrence(Task $task, Carbon $date, Carbon $start): bool
    {
        return match ($task->recurrence_pattern) {
            'daily' => true,
            'monthly' => $date->day === $start->day,
            default => $date->dayOfWeek === $start->dayOfWeek,
        };
    }

    protected function transformTaskOccurrence(Task $task, Carbon $date): array
    {
        return [
            'id' => 'task-' . $task->id . '-' . $date->toDateString(),
            'entity_id' => $task->id,
            'item_type' => 'task',
            'name' => $task->title,
            'event_date' => $date->toDateString(),
            'event_time' => 'Taak',
            'sort_time' => '06:00',
            'start_at' => $date->copy()->setTime(6, 0)->toIso8601String(),
            'end_at' => $date->copy()->setTime(7, 0)->toIso8601String(),
            'status' => $task->status,
            'status_label' => Task::statusOptions()[$task->status] ?? $task->status,
            'status_color' => $this->taskColor($task->status),
            'comment' => $task->description,
            'duration_label' => $task->task_type === Task::TYPE_RECURRING ? ('Herhalend · ' . ($task->recurrence_pattern === 'daily' ? 'Dagelijks' : ($task->recurrence_pattern === 'monthly' ? 'Maandelijks' : 'Wekelijks'))) : 'Eenmalig',
            'assigned_user_name' => $task->assignedUser?->name,
            'scheduled_by' => $task->scheduler?->name,
            'is_general' => !$task->assigned_user_id,
        ];
    }

    protected function resolveRange(string $view, Carbon $anchorDate): array
    {
        return match ($view) {
            'week' => [$anchorDate->copy()->startOfWeek(Carbon::MONDAY), $anchorDate->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchorDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY), $anchorDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)],
            default => [$anchorDate->copy(), $anchorDate->copy()],
        };
    }

    protected function buildRangeLabel(string $view, Carbon $rangeStart, Carbon $rangeEnd, Carbon $anchorDate): string
    {
        return match ($view) {
            'week' => ucfirst($rangeStart->locale('nl_BE')->isoFormat('D MMM')) . ' - ' . ucfirst($rangeEnd->locale('nl_BE')->isoFormat('D MMM YYYY')),
            'month' => ucfirst($anchorDate->locale('nl_BE')->isoFormat('MMMM YYYY')),
            default => ucfirst($anchorDate->locale('nl_BE')->isoFormat('dddd D MMMM YYYY')),
        };
    }

    protected function buildStatusTotals($registrations): array
    {
        $options = Registration::statusOptions();

        return collect($options)->map(function (string $label, string $status) use ($registrations) {
            $items = collect($registrations)->where('status', $status);
            return ['key' => $status, 'label' => $label, 'count' => $items->count(), 'people_count' => (int) $items->sum('total_count'), 'colors' => $this->statusColor($status)];
        })->filter(fn (array $item) => $item['count'] > 0)->values()->all();
    }

    protected function buildTaskTotals($tasks): array
    {
        return collect(Task::statusOptions())->map(function (string $label, string $status) use ($tasks) {
            $items = collect($tasks)->where('status', $status);
            return ['key' => $status, 'label' => $label, 'count' => $items->count(), 'colors' => $this->taskColor($status)];
        })->filter(fn (array $item) => $item['count'] > 0)->values()->all();
    }

    protected function buildCateringTotals($registrations): array
    {
        return collect($registrations)->filter(fn (array $registration) => !empty($registration['catering_option']))->groupBy('catering_option')->map(function ($items, $label) {
            $first = $items->first();
            return ['key' => $first['catering_option_code'] ?: md5((string) $label), 'label' => $label, 'emoji' => $first['catering_option_emoji'] ?? null, 'count' => $items->count(), 'people_count' => (int) $items->sum('total_count'), 'badge' => 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30', 'accent' => 'bg-cyan-500'];
        })->sortByDesc('people_count')->values()->all();
    }

    protected function transformRegistration(Registration $registration): array
    {
        $statusLabel = Registration::statusOptions()[$registration->status] ?? 'Onbekend';
        $start = $registration->event_date?->copy()->startOfDay();
        if ($start && $registration->event_time) {
            [$hour, $minute] = array_pad(explode(':', (string) $registration->event_time), 2, '00');
            $start->setTime((int) $hour, (int) $minute);
        }
        $end = $start?->copy();
        if ($end && $registration->stayOption?->duration_minutes) {
            $end->addMinutes((int) $registration->stayOption->duration_minutes);
        }
        $children = (int) $registration->participants_children;
        $adults = (int) $registration->participants_adults;
        $supervisors = (int) $registration->participants_supervisors;
        $totalCount = $children + $adults + $supervisors;

        return [
            'id' => $registration->id,
            'entity_id' => $registration->id,
            'item_type' => 'registration',
            'name' => $registration->name,
            'event_date' => optional($registration->event_date)->format('Y-m-d'),
            'event_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
            'sort_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : '23:59',
            'start_at' => $start?->toIso8601String(),
            'end_at' => $end?->toIso8601String(),
            'participants_children' => $children,
            'participants_adults' => $adults,
            'participants_supervisors' => $supervisors,
            'total_count' => $totalCount,
            'status' => $registration->status,
            'status_label' => $statusLabel,
            'status_color' => $this->statusColor($registration->status),
            'outside_opening_hours' => (bool) $registration->outside_opening_hours,
            'comment' => $registration->comment,
            'duration_label' => $registration->stayOption?->name,
            'stay_duration_minutes' => $registration->stayOption?->duration_minutes,
            'event_type' => $registration->eventType?->name,
            'event_type_code' => $registration->eventType?->code,
            'event_type_emoji' => $registration->eventType?->emoji,
            'catering_option' => $registration->cateringOption?->name,
            'catering_option_code' => $registration->cateringOption?->code,
            'catering_option_emoji' => $registration->cateringOption?->emoji,
            'invoice_requested' => (bool) $registration->invoice_requested,
        ];
    }

    protected function statusColor(?string $status): array
    {
        return match ($status) {
            Registration::STATUS_NEW => ['bg' => 'bg-yellow-500/10', 'border' => 'border-yellow-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-yellow-500', 'badge' => 'bg-yellow-500/15 text-yellow-300 ring-yellow-500/30'],
            Registration::STATUS_CONFIRMED => ['bg' => 'bg-orange-500/10', 'border' => 'border-orange-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-orange-500', 'badge' => 'bg-orange-500/15 text-orange-300 ring-orange-500/30'],
            Registration::STATUS_CHECKED_IN => ['bg' => 'bg-blue-500/10', 'border' => 'border-blue-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-blue-500', 'badge' => 'bg-blue-500/15 text-blue-300 ring-blue-500/30'],
            Registration::STATUS_CHECKED_OUT => ['bg' => 'bg-purple-500/10', 'border' => 'border-purple-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-purple-500', 'badge' => 'bg-purple-500/15 text-purple-300 ring-purple-500/30'],
            Registration::STATUS_PAID => ['bg' => 'bg-green-500/10', 'border' => 'border-green-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-green-500', 'badge' => 'bg-green-500/15 text-green-300 ring-green-500/30'],
            Registration::STATUS_CANCELLED => ['bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-slate-500', 'badge' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30'],
            Registration::STATUS_NO_SHOW => ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-red-500', 'badge' => 'bg-red-500/15 text-red-300 ring-red-500/30'],
            default => ['bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-slate-500', 'badge' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30'],
        };
    }

    protected function taskColor(?string $status): array
    {
        return match ($status) {
            Task::STATUS_COMPLETED => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'],
            Task::STATUS_CANCELLED => ['bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-slate-500', 'badge' => 'bg-slate-500/15 text-slate-300 ring-slate-500/30'],
            default => ['bg' => 'bg-pink-500/10', 'border' => 'border-pink-500/20', 'text' => 'text-slate-100', 'accent' => 'bg-pink-500', 'badge' => 'bg-pink-500/15 text-pink-300 ring-pink-500/30'],
        };
    }
}

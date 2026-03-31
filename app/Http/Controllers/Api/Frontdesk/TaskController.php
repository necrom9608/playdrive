<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();
        $assignedUserId = $request->filled('assigned_user_id') ? (int) $request->input('assigned_user_id') : null;

        $query = Task::query()
            ->with(['assignedUser:id,name', 'scheduler:id,name'])
            ->where('tenant_id', $currentTenant->id());

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($assignedUserId) {
            if ($assignedUserId === -1) {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', $assignedUserId);
            }
        }

        $tasks = $query
            ->orderByRaw("case when status = 'open' then 0 when status = 'completed' then 1 else 2 end")
            ->orderByRaw('coalesce(due_date, start_date, created_at) asc')
            ->orderByDesc('id')
            ->get();

        $baseQuery = Task::query()->where('tenant_id', $currentTenant->id());

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => (clone $baseQuery)->count(),
                    'open' => (clone $baseQuery)->where('status', Task::STATUS_OPEN)->count(),
                    'completed' => (clone $baseQuery)->where('status', Task::STATUS_COMPLETED)->count(),
                    'cancelled' => (clone $baseQuery)->where('status', Task::STATUS_CANCELLED)->count(),
                ],
                'staff' => User::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                    ->values(),
                'tasks' => $tasks->map(fn (Task $task) => $this->transformTask($task))->values(),
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $this->validatePayload($request);
        $userId = $this->frontdeskUserId($request);

        $task = Task::query()->create([
            'tenant_id' => $currentTenant->id(),
            'title' => $data['title'],
            'description' => $this->nullableValue($data['description'] ?? null),
            'status' => $data['status'] ?? Task::STATUS_OPEN,
            'task_type' => $data['task_type'],
            'recurrence_pattern' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['recurrence_pattern'] ?? 'weekly') : null,
            'due_date' => $data['task_type'] === Task::TYPE_SINGLE ? ($data['due_date'] ?? null) : null,
            'start_date' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['start_date'] ?? null) : null,
            'end_date' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['end_date'] ?? null) : null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'scheduled_by' => $userId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return response()->json(['data' => $this->transformTask($task->fresh(['assignedUser:id,name', 'scheduler:id,name']))], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, Task $task): JsonResponse
    {
        abort_unless((int) $task->tenant_id === (int) $currentTenant->id(), 404);

        $data = $this->validatePayload($request);
        $userId = $this->frontdeskUserId($request);

        $attributes = [
            'title' => $data['title'],
            'description' => $this->nullableValue($data['description'] ?? null),
            'status' => $data['status'] ?? Task::STATUS_OPEN,
            'task_type' => $data['task_type'],
            'recurrence_pattern' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['recurrence_pattern'] ?? 'weekly') : null,
            'due_date' => $data['task_type'] === Task::TYPE_SINGLE ? ($data['due_date'] ?? null) : null,
            'start_date' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['start_date'] ?? null) : null,
            'end_date' => $data['task_type'] === Task::TYPE_RECURRING ? ($data['end_date'] ?? null) : null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'updated_by' => $userId,
        ];

        if (($data['status'] ?? $task->status) === Task::STATUS_COMPLETED && !$task->completed_at) {
            $attributes['completed_at'] = now();
            $attributes['completed_by'] = $userId;
        }

        if (($data['status'] ?? $task->status) === Task::STATUS_CANCELLED && !$task->cancelled_at) {
            $attributes['cancelled_at'] = now();
            $attributes['cancelled_by'] = $userId;
        }

        if (($data['status'] ?? $task->status) === Task::STATUS_OPEN) {
            $attributes['completed_at'] = null;
            $attributes['completed_by'] = null;
            $attributes['cancelled_at'] = null;
            $attributes['cancelled_by'] = null;
        }

        $task->update($attributes);

        return response()->json(['data' => $this->transformTask($task->fresh(['assignedUser:id,name', 'scheduler:id,name']))]);
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,completed,cancelled'],
            'task_type' => ['required', 'in:single,recurring'],
            'recurrence_pattern' => ['nullable', 'in:daily,weekly,monthly'],
            'due_date' => ['nullable', 'date', 'required_if:task_type,single'],
            'start_date' => ['nullable', 'date', 'required_if:task_type,recurring'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    protected function transformTask(Task $task): array
    {
        $isRecurring = $task->task_type === Task::TYPE_RECURRING;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'status_label' => Task::statusOptions()[$task->status] ?? $task->status,
            'task_type' => $task->task_type,
            'task_type_label' => $isRecurring ? 'Herhalend' : 'Eenmalig',
            'recurrence_pattern' => $task->recurrence_pattern,
            'recurrence_label' => match ($task->recurrence_pattern) {
                'daily' => 'Dagelijks',
                'weekly' => 'Wekelijks',
                'monthly' => 'Maandelijks',
                default => null,
            },
            'due_date' => optional($task->due_date)->format('Y-m-d'),
            'due_date_label' => optional($task->due_date)->format('d/m/Y'),
            'start_date' => optional($task->start_date)->format('Y-m-d'),
            'start_date_label' => optional($task->start_date)->format('d/m/Y'),
            'end_date' => optional($task->end_date)->format('Y-m-d'),
            'end_date_label' => optional($task->end_date)->format('d/m/Y'),
            'assigned_user_id' => $task->assigned_user_id,
            'assigned_user_name' => $task->assignedUser?->name,
            'scheduled_by' => $task->scheduler?->name,
            'created_at' => optional($task->created_at)->toIso8601String(),
        ];
    }

    protected function frontdeskUserId(Request $request): ?int
    {
        return $request->attributes->get('frontdesk_user')?->id;
    }

    protected function nullableValue(mixed $value): mixed
    {
        return filled($value) ? $value : null;
    }
}

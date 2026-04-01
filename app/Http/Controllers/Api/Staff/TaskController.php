<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Api\Frontdesk\TaskController as FrontdeskTaskController;
use App\Models\Task;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends FrontdeskTaskController
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $request->attributes->get('staff_user');
        $search = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();

        $query = Task::query()
            ->with(['assignedUser:id,name', 'scheduler:id,name'])
            ->where('tenant_id', $currentTenant->id())
            ->where(function ($builder) use ($user) {
                $builder->where('assigned_user_id', $user->id)
                    ->orWhereNull('assigned_user_id');
            });

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $tasks = $query
            ->orderByRaw("case when status = 'open' then 0 when status = 'completed' then 1 else 2 end")
            ->orderByRaw('coalesce(due_date, start_date, created_at) asc')
            ->get();

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => $tasks->count(),
                    'open' => $tasks->where('status', Task::STATUS_OPEN)->count(),
                    'completed' => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
                    'cancelled' => $tasks->where('status', Task::STATUS_CANCELLED)->count(),
                ],
                'tasks' => $tasks->map(fn (Task $task) => $this->transformTask($task))->values(),
            ],
        ]);
    }

    public function update(Request $request, CurrentTenant $currentTenant, Task $task): JsonResponse
    {
        $user = $request->attributes->get('staff_user');
        abort_unless((int) $task->tenant_id === (int) $currentTenant->id(), 404);
        abort_unless($task->assigned_user_id === null || (int) $task->assigned_user_id === (int) $user->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:open,completed,cancelled'],
        ]);

        $attributes = [
            'status' => $data['status'],
            'updated_by' => $user->id,
        ];

        if ($data['status'] === Task::STATUS_COMPLETED) {
            $attributes['completed_at'] = now();
            $attributes['completed_by'] = $user->id;
        }

        if ($data['status'] === Task::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = now();
            $attributes['cancelled_by'] = $user->id;
        }

        if ($data['status'] === Task::STATUS_OPEN) {
            $attributes['completed_at'] = null;
            $attributes['completed_by'] = null;
            $attributes['cancelled_at'] = null;
            $attributes['cancelled_by'] = null;
        }

        if ($task->assigned_user_id === null) {
            $attributes['assigned_user_id'] = $user->id;
        }

        $task->update($attributes);

        return response()->json(['data' => $this->transformTask($task->fresh(['assignedUser:id,name', 'scheduler:id,name']))]);
    }
}

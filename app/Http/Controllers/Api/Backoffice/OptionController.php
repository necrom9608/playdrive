<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreCatalogOptionRequest;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OptionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $modelClass = $this->resolveModelClass($request->route('type'));

        $items = $this->tenantAwareQuery($modelClass, $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function store(StoreCatalogOptionRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $modelClass = $this->resolveModelClass($request->route('type'));
        /** @var Model $item */
        $item = new $modelClass();
        $item->fill($this->payload($request, $item, $currentTenant->id()));
        $item->save();

        return response()->json($item->fresh(), 201);
    }

    public function update(StoreCatalogOptionRequest $request, CurrentTenant $currentTenant, string $type, int $item): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        /** @var Model $record */
        $record = $this->tenantAwareQuery($modelClass, $currentTenant->id())
            ->findOrFail($item);

        $record->update($this->payload($request, $record, $currentTenant->id()));

        return response()->json($record->fresh());
    }

    public function reorder(Request $request, CurrentTenant $currentTenant, string $type): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ])['items'];

        foreach ($items as $index => $item) {
            $this->tenantAwareQuery($modelClass, $currentTenant->id())
                ->whereKey($item['id'] ?? null)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(CurrentTenant $currentTenant, string $type, int $item): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        $this->tenantAwareQuery($modelClass, $currentTenant->id())
            ->findOrFail($item)
            ->delete();

        return response()->json(['success' => true]);
    }

    protected function payload(StoreCatalogOptionRequest $request, Model $record, int $tenantId): array
    {
        $nextSortOrder = (int) $this->tenantAwareQuery($record::class, $tenantId)->max('sort_order') + 1;

        $payload = [
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code')
                ? Str::slug($request->string('code')->toString(), '_')
                : Str::slug($request->string('name')->toString(), '_'),
            'emoji' => $this->nullableValue($request->input('emoji')),
            'sort_order' => $request->integer('sort_order', $record->exists ? $record->getAttribute('sort_order') : $nextSortOrder),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($this->modelHasTenantColumn($record::class)) {
            $payload['tenant_id'] = $tenantId;
        }

        if ($record->getAttribute('duration_minutes') !== null || $request->has('duration_minutes')) {
            $payload['duration_minutes'] = $request->filled('duration_minutes')
                ? $request->integer('duration_minutes', 0)
                : null;
        }

        return $payload;
    }


    protected function tenantAwareQuery(string $modelClass, ?int $tenantId)
    {
        $query = $modelClass::query();

        if ($tenantId !== null && $this->modelHasTenantColumn($modelClass)) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    protected function modelHasTenantColumn(string $modelClass): bool
    {
        $model = new $modelClass();

        return Schema::hasColumn($model->getTable(), 'tenant_id');
    }

    protected function resolveModelClass(string $type): string
    {
        return match ($type) {
            'catering-options' => \App\Models\CateringOption::class,
            'event-types' => \App\Models\EventType::class,
            'stay-options' => \App\Models\StayOption::class,
            default => abort(404),
        };
    }

    private function nullableValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }
}

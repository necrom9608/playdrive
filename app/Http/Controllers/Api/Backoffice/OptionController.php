<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreCatalogOptionRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $modelClass = $this->resolveModelClass($request->route('type'));

        $items = $modelClass::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function store(StoreCatalogOptionRequest $request): JsonResponse
    {
        $modelClass = $this->resolveModelClass($request->route('type'));

        /** @var Model $item */
        $item = $modelClass::query()->create($this->payload($request, new $modelClass()));

        return response()->json($item, 201);
    }

    public function update(StoreCatalogOptionRequest $request, string $type, int $item): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        /** @var Model $record */
        $record = $modelClass::query()->findOrFail($item);
        $record->update($this->payload($request, $record));

        return response()->json($record->fresh());
    }

    public function reorder(Request $request, string $type): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        $items = $request->input('items', []);

        foreach ($items as $index => $item) {
            $modelClass::query()->whereKey($item['id'] ?? null)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(string $type, int $item): JsonResponse
    {
        $modelClass = $this->resolveModelClass($type);
        $modelClass::query()->findOrFail($item)->delete();

        return response()->json(['success' => true]);
    }

    protected function payload(StoreCatalogOptionRequest $request, Model $record): array
    {
        $nextSortOrder = (int) $record::query()->max('sort_order') + 1;

        $payload = [
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code')
                ? Str::slug($request->string('code')->toString(), '_')
                : Str::slug($request->string('name')->toString(), '_'),
            'emoji' => $request->input('emoji'),
            'sort_order' => $request->integer('sort_order', $record->exists ? $record->getAttribute('sort_order') : $nextSortOrder),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($record->getAttribute('duration_minutes') !== null || $request->has('duration_minutes')) {
            $payload['duration_minutes'] = $request->integer('duration_minutes', 0);
        }

        return $payload;
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
}

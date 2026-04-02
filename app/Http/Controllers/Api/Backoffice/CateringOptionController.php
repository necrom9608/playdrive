<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreCatalogOptionRequest;
use App\Models\CateringOption;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CateringOptionController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $options = CateringOption::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CateringOption $option) => $this->mapOption($option))
            ->values();

        return response()->json($options);
    }

    public function store(StoreCatalogOptionRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $nextSortOrder = (int) CateringOption::query()
            ->where('tenant_id', $currentTenant->id())
            ->max('sort_order');

        $option = CateringOption::query()->create([
            'tenant_id' => $currentTenant->id(),
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code') ? Str::slug($request->string('code')->toString(), '_') : Str::slug($request->string('name')->toString(), '_'),
            'emoji' => $this->nullableValue($request->input('emoji')),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $nextSortOrder + 1,
        ]);

        return response()->json($this->mapOption($option), 201);
    }

    public function update(StoreCatalogOptionRequest $request, CurrentTenant $currentTenant, CateringOption $cateringOption): JsonResponse
    {
        abort_unless((int) $cateringOption->tenant_id === (int) $currentTenant->id(), 404);

        $cateringOption->update([
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code') ? Str::slug($request->string('code')->toString(), '_') : Str::slug($request->string('name')->toString(), '_'),
            'emoji' => $this->nullableValue($request->input('emoji')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($this->mapOption($cateringOption->fresh()));
    }

    public function destroy(CurrentTenant $currentTenant, CateringOption $cateringOption): JsonResponse
    {
        abort_unless((int) $cateringOption->tenant_id === (int) $currentTenant->id(), 404);

        $cateringOption->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $ids = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->values();
        $existingIds = CateringOption::query()->where('tenant_id', $currentTenant->id())->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($existingIds->count() !== $ids->count()) {
            return response()->json(['message' => 'Ongeldige cateringoptielijst voor herschikken.'], 422);
        }

        DB::transaction(function () use ($ids, $currentTenant) {
            foreach ($ids as $index => $id) {
                CateringOption::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function mapOption(CateringOption $option): array
    {
        return [
            'id' => $option->id,
            'name' => $option->name,
            'code' => $option->code,
            'emoji' => $option->emoji,
            'is_active' => (bool) $option->is_active,
            'sort_order' => (int) $option->sort_order,
        ];
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

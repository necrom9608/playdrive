<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreCateringOptionProductRequest;
use App\Http\Requests\Backoffice\UpdateCateringOptionProductRequest;
use App\Models\CateringOption;
use App\Models\CateringOptionProduct;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CateringOptionProductController extends Controller
{
    public function index(CateringOption $cateringOption, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $cateringOption->tenant_id === (int) $currentTenant->id(), 404);

        $links = CateringOptionProduct::query()
            ->with('product')
            ->where('tenant_id', $currentTenant->id())
            ->where('catering_option_id', $cateringOption->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CateringOptionProduct $link) => $this->mapLink($link))
            ->values();

        return response()->json($links);
    }

    public function store(
        StoreCateringOptionProductRequest $request,
        CateringOption $cateringOption,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless((int) $cateringOption->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();

        $product = Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail($data['product_id']);

        $nextSortOrder = (int) CateringOptionProduct::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('catering_option_id', $cateringOption->id)
            ->max('sort_order');

        $link = CateringOptionProduct::query()->create([
            'tenant_id' => $currentTenant->id(),
            'catering_option_id' => $cateringOption->id,
            'product_id' => $product->id,
            'applies_to_children' => (bool) $data['applies_to_children'],
            'applies_to_adults' => (bool) $data['applies_to_adults'],
            'quantity_per_person' => $data['quantity_per_person'],
            'sort_order' => $nextSortOrder + 1,
        ]);

        $link->load('product');

        return response()->json($this->mapLink($link), 201);
    }

    public function update(
        UpdateCateringOptionProductRequest $request,
        CateringOptionProduct $cateringOptionProduct,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless((int) $cateringOptionProduct->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validated();

        $product = Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail($data['product_id']);

        $cateringOptionProduct->update([
            'product_id' => $product->id,
            'applies_to_children' => (bool) $data['applies_to_children'],
            'applies_to_adults' => (bool) $data['applies_to_adults'],
            'quantity_per_person' => $data['quantity_per_person'],
        ]);

        $cateringOptionProduct->load('product');

        return response()->json($this->mapLink($cateringOptionProduct));
    }

    public function destroy(CateringOptionProduct $cateringOptionProduct, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $cateringOptionProduct->tenant_id === (int) $currentTenant->id(), 404);

        $cateringOptionProduct->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function reorder(
        Request $request,
        CateringOption $cateringOption,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless((int) $cateringOption->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $ids = collect($data['items'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $existingIds = CateringOptionProduct::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('catering_option_id', $cateringOption->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($existingIds->count() !== $ids->count()) {
            return response()->json([
                'message' => 'Ongeldige productkoppelingen voor herschikken.',
            ], 422);
        }

        DB::transaction(function () use ($ids, $currentTenant, $cateringOption) {
            foreach ($ids as $index => $id) {
                CateringOptionProduct::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('catering_option_id', $cateringOption->id)
                    ->where('id', $id)
                    ->update([
                        'sort_order' => $index + 1,
                    ]);
            }
        });

        return response()->json([
            'success' => true,
        ]);
    }

    private function mapLink(CateringOptionProduct $link): array
    {
        return [
            'id' => $link->id,
            'catering_option_id' => $link->catering_option_id,
            'product_id' => $link->product_id,
            'product_name' => $link->product?->name,
            'applies_to_children' => (bool) $link->applies_to_children,
            'applies_to_adults' => (bool) $link->applies_to_adults,
            'quantity_per_person' => (float) $link->quantity_per_person,
            'sort_order' => (int) $link->sort_order,
        ];
    }
}

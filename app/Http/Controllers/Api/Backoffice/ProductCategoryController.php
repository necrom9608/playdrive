<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Catalog\CatalogSlugService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreProductCategoryRequest;
use App\Http\Requests\Backoffice\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $categories = ProductCategory::query()
            ->where('tenant_id', $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(
        StoreProductCategoryRequest $request,
        CatalogSlugService $slugService,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_if(! $currentTenant->exists(), 500, 'No current tenant resolved.');

        $category = ProductCategory::create([
            'tenant_id' => $currentTenant->id(),
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? $slugService->makeSlug($request->string('slug')->toString())
                : $slugService->makeSlug($request->string('name')->toString()),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return response()->json($category, 201);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $productCategory,
        CatalogSlugService $slugService,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_if($productCategory->tenant_id !== $currentTenant->id(), 404);

        $productCategory->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? $slugService->makeSlug($request->string('slug')->toString())
                : $slugService->makeSlug($request->string('name')->toString()),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($productCategory);
    }

    public function destroy(ProductCategory $productCategory, CurrentTenant $currentTenant): JsonResponse
    {
        abort_if($productCategory->tenant_id !== $currentTenant->id(), 404);

        $productCategory->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $items = $request->input('items', []);

        foreach ($items as $index => $item) {
            ProductCategory::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('id', $item['id'])
                ->update([
                    'sort_order' => $index + 1,
                ]);
        }

        return response()->json(['success' => true]);
    }
}

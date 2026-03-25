<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Catalog\CatalogSlugService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreProductCategoryRequest;
use App\Models\ProductCategory;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = $currentTenant->id();

        $categories = ProductCategory::query()
            ->where('tenant_id', $tenantId)
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
            'sort_order' => $request->integer('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($category, 201);
    }

    public function update(
        StoreProductCategoryRequest $request,
        ProductCategory $productCategory,
        CatalogSlugService $slugService,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless(
            $currentTenant->exists() && $productCategory->tenant_id === $currentTenant->id(),
            404
        );

        $productCategory->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? $slugService->makeSlug($request->string('slug')->toString())
                : $slugService->makeSlug($request->string('name')->toString()),
            'sort_order' => $request->integer('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($productCategory);
    }

    public function destroy(
        ProductCategory $productCategory,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless(
            $currentTenant->exists() && $productCategory->tenant_id === $currentTenant->id(),
            404
        );

        $productCategory->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}

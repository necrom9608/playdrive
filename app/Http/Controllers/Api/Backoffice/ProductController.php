<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Catalog\CatalogSlugService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreProductRequest;
use App\Http\Requests\Backoffice\UpdateProductRequest;
use App\Models\Product;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            ->where('tenant_id', $currentTenant->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'product_category_id' => $product->product_category_id,
                    'category_name' => $product->category?->name,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'image_path' => $product->image_path,
                    'image_url' => $product->image_url,
                    'price_excl_vat' => $product->price_excl_vat,
                    'vat_rate' => $product->vat_rate,
                    'price_incl_vat' => $product->price_incl_vat,
                    'is_active' => $product->is_active,
                    'sort_order' => $product->sort_order,
                ];
            });

        return response()->json($products);
    }

    public function store(
        StoreProductRequest $request,
        CatalogSlugService $slugService,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_if(! $currentTenant->exists(), 500, 'No current tenant resolved.');

        $product = Product::create([
            'tenant_id' => $currentTenant->id(),
            'product_category_id' => $request->input('product_category_id'),
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? $slugService->makeSlug($request->string('slug')->toString())
                : $slugService->makeSlug($request->string('name')->toString()),
            'description' => $request->input('description'),
            'image_path' => $this->storeUploadedImage($request),
            'price_excl_vat' => $this->resolvePriceExclVat($request),
            'price_incl_vat' => $this->resolvePriceInclVat($request),
            'vat_rate' => $request->input('vat_rate'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return response()->json($product->load('category'), 201);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        CatalogSlugService $slugService,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_if($product->tenant_id !== $currentTenant->id(), 404);

        $imagePath = $product->image_path;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $this->storeUploadedImage($request);
        }

        $product->update([
            'product_category_id' => $request->input('product_category_id'),
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug')
                ? $slugService->makeSlug($request->string('slug')->toString())
                : $slugService->makeSlug($request->string('name')->toString()),
            'description' => $request->input('description'),
            'image_path' => $imagePath,
            'price_excl_vat' => $this->resolvePriceExclVat($request),
            'price_incl_vat' => $this->resolvePriceInclVat($request),
            'vat_rate' => $request->input('vat_rate'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json($product->load('category'));
    }

    public function destroy(Product $product, CurrentTenant $currentTenant): JsonResponse
    {
        abort_if($product->tenant_id !== $currentTenant->id(), 404);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $items = $request->input('items', []);

        foreach ($items as $index => $item) {
            Product::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('id', $item['id'])
                ->update([
                    'sort_order' => $index + 1,
                ]);
        }

        return response()->json(['success' => true]);
    }

    protected function resolvePriceInclVat(Request $request): float
    {
        if ($request->filled('price_incl_vat')) {
            return round((float) $request->input('price_incl_vat'), 2);
        }

        $priceExclVat = (float) $request->input('price_excl_vat', 0);
        $vatRate = (float) $request->input('vat_rate', 0);

        return round($priceExclVat * (1 + ($vatRate / 100)), 2);
    }

    protected function resolvePriceExclVat(Request $request): float
    {
        if ($request->filled('price_excl_vat')) {
            return round((float) $request->input('price_excl_vat'), 2);
        }

        $priceInclVat = (float) $request->input('price_incl_vat', 0);
        $vatRate = (float) $request->input('vat_rate', 0);

        if ($vatRate <= 0) {
            return round($priceInclVat, 2);
        }

        return round($priceInclVat / (1 + ($vatRate / 100)), 2);
    }

    protected function storeUploadedImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('products', 'public');
    }
}

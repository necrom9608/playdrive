<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function products(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }
}

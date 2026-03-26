<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q', ''));

        if ($query === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $locations = Location::query()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('city', 'like', '%' . $query . '%')
                    ->orWhere('postal_code', 'like', '%' . $query . '%');
            })
            ->orderBy('postal_code')
            ->orderBy('city')
            ->limit(20)
            ->get(['id', 'country', 'postal_code', 'city']);

        return response()->json([
            'data' => $locations,
        ]);
    }
}

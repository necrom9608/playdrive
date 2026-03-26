<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\CateringOption;
use App\Models\EventType;
use App\Models\StayOption;
use Illuminate\Http\JsonResponse;

class FormOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $eventTypes = EventType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $stayOptions = StayOption::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'duration_minutes']);

        $cateringOptions = CateringOption::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'event_types' => $eventTypes,
            'stay_options' => $stayOptions,
            'catering_options' => $cateringOptions,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\PricingEvaluator;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        protected PricingEvaluator $pricingEvaluator,
    ) {
    }

    public function evaluate(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'registration_id' => ['required', 'integer'],
            'checked_out_at' => ['nullable', 'date'],
        ]);

        $registration = Registration::query()->findOrFail($validated['registration_id']);

        if (! $currentTenant->exists() || (int) $registration->tenant_id !== (int) $currentTenant->id()) {
            return response()->json([
                'message' => 'Registratie niet gevonden voor deze tenant.',
            ], 404);
        }

        $context = PricingContext::fromRegistration(
            $registration,
            $validated['checked_out_at'] ?? null,
        );

        $result = $this->pricingEvaluator->evaluate($context);

        return response()->json([
            'data' => $this->pricingEvaluator->enrichWithProducts($result),
        ]);
    }
}

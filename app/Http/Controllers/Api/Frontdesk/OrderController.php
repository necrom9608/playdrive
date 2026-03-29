<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Orders\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {
    }


    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        if (! $currentTenant->exists()) {
            return response()->json([
                'data' => [],
            ]);
        }

        $orders = Order::query()
            ->with(['items.product'])
            ->where('tenant_id', $currentTenant->id())
            ->where('status', Order::STATUS_OPEN)
            ->latest('id')
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'context' => $order->source === Order::SOURCE_RESERVATION ? 'reservation' : 'walk_in',
                    'reservation_id' => $order->registration_id,
                    'subtotal_excl_vat' => (float) $order->subtotal_excl_vat,
                    'total_vat' => (float) $order->total_vat,
                    'total_incl_vat' => (float) $order->total_incl_vat,
                    'items' => $order->items
                        ->sortBy('sort_order')
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'line_id' => 'order-item-' . $item->id,
                            'product_id' => $item->product_id,
                            'name' => $item->name,
                            'price_incl_vat' => (float) $item->unit_price_incl_vat,
                            'quantity' => (int) $item->quantity,
                            'source' => $item->source,
                            'source_reference' => $item->source_reference,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function checkout(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'invoice_requested' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->orderService->checkout($validated, $currentTenant);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'total_incl_vat' => (float) $order->total_incl_vat,
                'payment_method' => $order->payment_method,
                'invoice_requested' => (bool) $order->invoice_requested,
            ],
        ]);
    }

    public function cancel(Request $request, CurrentTenant $currentTenant, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $currentTenant->exists() || $order->tenant_id !== $currentTenant->id()) {
            return response()->json([
                'message' => 'Order niet gevonden voor deze tenant.',
            ], 404);
        }

        if ($order->status === Order::STATUS_CANCELLED || $order->cancelled_at) {
            return response()->json([
                'message' => 'Dit order is al geannuleerd.',
            ], 422);
        }

        if ($order->refunded_at) {
            return response()->json([
                'message' => 'Een terugbetaald order kan niet meer geannuleerd worden.',
            ], 422);
        }

        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => filled($validated['reason'] ?? null) ? $validated['reason'] : null,
        ]);

        return response()->json([
            'message' => 'Order werd geannuleerd.',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'cancelled_at' => optional($order->cancelled_at)->toIso8601String(),
            ],
        ]);
    }

    public function refund(Request $request, CurrentTenant $currentTenant, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'refund_method' => ['required', 'string', 'max:50'],
        ]);

        if (! $currentTenant->exists() || $order->tenant_id !== $currentTenant->id()) {
            return response()->json([
                'message' => 'Order niet gevonden voor deze tenant.',
            ], 404);
        }

        if ($order->status === Order::STATUS_CANCELLED || $order->cancelled_at) {
            return response()->json([
                'message' => 'Een geannuleerd order kan niet terugbetaald worden.',
            ], 422);
        }

        if ($order->refunded_at) {
            return response()->json([
                'message' => 'Dit order werd al terugbetaald.',
            ], 422);
        }

        if ($order->status !== Order::STATUS_PAID) {
            return response()->json([
                'message' => 'Alleen betaalde orders kunnen terugbetaald worden.',
            ], 422);
        }

        $order->update([
            'refunded_at' => now(),
            'refunded_by' => Auth::id(),
            'refund_amount' => $order->total_incl_vat,
            'refund_method' => $validated['refund_method'],
            'refund_reason' => filled($validated['reason'] ?? null) ? $validated['reason'] : null,
        ]);

        return response()->json([
            'message' => 'Terugbetaling werd geregistreerd.',
            'data' => [
                'id' => $order->id,
                'refunded_at' => optional($order->refunded_at)->toIso8601String(),
                'refund_amount' => (float) $order->refund_amount,
                'refund_method' => $order->refund_method,
            ],
        ]);
    }
}

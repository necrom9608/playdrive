<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Orders\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->with([
                'items.product',
                'creator:id,name',
                'updater:id,name',
                'payer:id,name',
                'canceller:id,name',
                'refunder:id,name',
                'items.creator:id,name',
                'items.updater:id,name',
            ])
            ->where('tenant_id', $currentTenant->id())
            ->where('status', Order::STATUS_OPEN)
            ->latest('id')
            ->get()
            ->map(fn (Order $order) => $this->transformOrder($order))
            ->values();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function addItem(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => ['nullable', 'integer'],
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $this->orderService->addManualItem($validated, $currentTenant);

        return response()->json([
            'data' => $this->transformOrder($order),
        ]);
    }

    public function updateItem(Request $request, CurrentTenant $currentTenant, Order $order, OrderItem $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $updatedOrder = $this->orderService->updateOrderItemQuantity(
            $order,
            $item,
            (int) $validated['quantity'],
            $currentTenant,
        );

        return response()->json([
            'data' => $this->transformOrder($updatedOrder),
        ]);
    }

    public function deleteItem(CurrentTenant $currentTenant, Order $order, OrderItem $item): JsonResponse
    {
        $updatedOrder = $this->orderService->removeOrderItem($order, $item, $currentTenant);

        return response()->json([
            'data' => $this->transformOrder($updatedOrder),
        ]);
    }

    public function checkout(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'reservation_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'invoice_requested' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
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
            'cancelled_by' => $this->frontdeskUserId($request),
            'updated_by' => $this->frontdeskUserId($request),
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
            'refunded_by' => $this->frontdeskUserId($request),
            'updated_by' => $this->frontdeskUserId($request),
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

    protected function frontdeskUserId(Request $request): ?int
    {
        return $request->attributes->get('frontdesk_user')?->id;
    }

    protected function transformActor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    public function transformOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'context' => $order->source === Order::SOURCE_RESERVATION ? 'reservation' : 'walk_in',
            'reservation_id' => $order->registration_id,
            'subtotal_excl_vat' => (float) $order->subtotal_excl_vat,
            'total_vat' => (float) $order->total_vat,
            'total_incl_vat' => (float) $order->total_incl_vat,
            'created_by' => $this->transformActor($order->creator),
            'updated_by' => $this->transformActor($order->updater),
            'paid_by' => $this->transformActor($order->payer),
            'cancelled_by' => $this->transformActor($order->canceller),
            'refunded_by' => $this->transformActor($order->refunder),
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
                    'created_by' => $this->transformActor($item->creator),
                    'updated_by' => $this->transformActor($item->updater),
                ])
                ->values()
                ->all(),
        ];
    }
}

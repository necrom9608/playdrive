<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        if (! $currentTenant->exists()) {
            return response()->json([
                'message' => 'Geen tenant gevonden voor het verkoopscherm.',
            ], 422);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'invoice_requested' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $selectedDate = $validated['date'] ?? now()->format('Y-m-d');
        $day = Carbon::createFromFormat('Y-m-d', $selectedDate);

        $query = Order::query()
            ->with([
                'registration:id,name,invoice_requested,invoice_company_name',
                'items:id,order_id,name,quantity,line_total_incl_vat',
                'creator:id,name',
                'canceller:id,name',
                'refunder:id,name',
            ])
            ->where('tenant_id', $currentTenant->id())
            ->whereDate('paid_at', $day->toDateString())
            ->latest('paid_at')
            ->latest('id');

        if (array_key_exists('invoice_requested', $validated)) {
            $query->where('invoice_requested', (bool) $validated['invoice_requested']);
        }

        if (! empty($validated['payment_method'])) {
            $query->where('payment_method', $validated['payment_method']);
        }

        if (! empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        $orders = $query->get();

        $activeOrders = $orders->filter(fn (Order $order) => ! $order->cancelled_at);
        $refundedOrders = $activeOrders->filter(fn (Order $order) => $order->refunded_at);

        $grossRevenue = (float) $activeOrders->sum('total_incl_vat');
        $refundedTotal = (float) $refundedOrders->sum('refund_amount');
        $netRevenue = round($grossRevenue - $refundedTotal, 2);

        $summary = [
            'selected_date' => $day->toDateString(),
            'total_orders' => $orders->count(),
            'active_order_count' => $activeOrders->count(),
            'cancelled_order_count' => $orders->whereNotNull('cancelled_at')->count(),
            'refunded_order_count' => $refundedOrders->count(),

            'gross_revenue' => round($grossRevenue, 2),
            'refunded_total' => round($refundedTotal, 2),
            'net_revenue' => $netRevenue,

            'cash_revenue' => round((float) $activeOrders->where('payment_method', 'cash')->sum('total_incl_vat'), 2),
            'bancontact_revenue' => round((float) $activeOrders->where('payment_method', 'bancontact')->sum('total_incl_vat'), 2),
            'invoice_revenue' => round((float) $activeOrders->where('invoice_requested', true)->sum('total_incl_vat'), 2),
            'non_invoice_revenue' => round((float) $activeOrders->where('invoice_requested', false)->sum('total_incl_vat'), 2),
            'invoice_order_count' => $activeOrders->where('invoice_requested', true)->count(),
            'walk_in_order_count' => $activeOrders->where('source', Order::SOURCE_WALK_IN)->count(),
            'reservation_order_count' => $activeOrders->where('source', Order::SOURCE_RESERVATION)->count(),
        ];

        $sales = $orders->map(function (Order $order) {
            return [
                'id' => $order->id,
                'paid_at' => optional($order->paid_at)?->toIso8601String(),
                'paid_time' => optional($order->paid_at)?->format('H:i'),
                'status' => $order->status,
                'source' => $order->source,
                'source_label' => $order->source === Order::SOURCE_RESERVATION ? 'Reservatie' : 'Losse verkoop',
                'payment_method' => $order->payment_method,
                'invoice_requested' => (bool) $order->invoice_requested,
                'subtotal_excl_vat' => (float) $order->subtotal_excl_vat,
                'total_vat' => (float) $order->total_vat,
                'total_incl_vat' => (float) $order->total_incl_vat,
                'notes' => $order->notes,

                'cancelled_at' => optional($order->cancelled_at)?->toIso8601String(),
                'cancellation_reason' => $order->cancellation_reason,

                'refunded_at' => optional($order->refunded_at)?->toIso8601String(),
                'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
                'refund_method' => $order->refund_method,
                'refund_reason' => $order->refund_reason,

                'registration' => $order->registration ? [
                    'id' => $order->registration->id,
                    'name' => $order->registration->name,
                    'invoice_requested' => (bool) $order->registration->invoice_requested,
                    'invoice_company_name' => $order->registration->invoice_company_name,
                ] : null,

                'creator' => $order->creator ? [
                    'name' => $order->creator->name,
                ] : null,

                'canceller' => $order->canceller ? [
                    'name' => $order->canceller->name,
                ] : null,

                'refunder' => $order->refunder ? [
                    'name' => $order->refunder->name,
                ] : null,

                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => (int) $item->quantity,
                    'line_total_incl_vat' => (float) $item->line_total_incl_vat,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'orders' => $sales,
            ],
        ]);
    }
}

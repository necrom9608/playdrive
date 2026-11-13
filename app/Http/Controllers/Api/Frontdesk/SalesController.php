<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Invoicing\OrderInvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderInvoice;
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
            'order_id' => ['nullable', 'integer', 'min:1'],
            'open_invoices' => ['nullable', 'boolean'],
        ]);
        $openInvoices = (bool) ($validated['open_invoices'] ?? false);

        $selectedDate = $validated['date'] ?? now()->format('Y-m-d');

        // Zoeken op bonnummer (bv. klant komt later een factuur vragen): naar de dag van die bon springen.
        if (! empty($validated['order_id'])) {
            $found = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->whereNotNull('paid_at')
                ->find((int) $validated['order_id']);

            if (! $found) {
                return response()->json(['message' => "Bon #{$validated['order_id']} niet gevonden (of nog niet afgerekend)."], 404);
            }

            $selectedDate = $found->paid_at->format('Y-m-d');
            unset($validated['invoice_requested'], $validated['payment_method'], $validated['source']);
        }
        $day = Carbon::createFromFormat('Y-m-d', $selectedDate);

        $query = Order::query()
            ->with([
                'registration:id,name,email,invoice_requested,invoice_company_name,invoice_vat_number,invoice_email,invoice_address,invoice_postal_code,invoice_city,invoice_po_number,invoice_comment',
                'invoice',
                'items:id,order_id,name,quantity,line_total_incl_vat,source,source_reference,created_by,updated_by',
                'creator:id,name',
                'updater:id,name',
                'payer:id,name',
                'canceller:id,name',
                'refunder:id,name',
                'items.creator:id,name',
                'items.updater:id,name',
            ])
            ->where('tenant_id', $currentTenant->id())
            // Openstaande facturen: over alle dagen heen; anders één dag
            ->when(
                $openInvoices,
                fn ($q) => $q->whereHas('invoice', fn ($i) => $i->whereIn('payment_status', OrderInvoiceService::PAY_UNSETTLED)),
                fn ($q) => $q->whereDate('paid_at', $day->toDateString()),
            )
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
            'invoicing_enabled' => OrderInvoiceService::configured($currentTenant->tenant),
            'on_invoice_revenue' => round((float) $activeOrders->where('payment_method', OrderInvoiceService::METHOD_ON_INVOICE)->sum('total_incl_vat'), 2),
            'open_invoices' => $this->openInvoiceSummary((int) $currentTenant->id()),
            'showing_open_invoices' => $openInvoices,
        ];

        $sales = $orders->map(function (Order $order) {
            return [
                'id' => $order->id,
                'paid_at' => optional($order->paid_at)?->toIso8601String(),
                'paid_time' => optional($order->paid_at)?->format('H:i'),
                'paid_date' => optional($order->paid_at)?->format('d/m/Y'),
                'status' => $order->status,
                'source' => $order->source,
                'source_label' => $order->source === Order::SOURCE_RESERVATION ? 'Reservatie' : 'Losse verkoop',
                'payment_method' => $order->payment_method,
                'invoice_requested' => (bool) $order->invoice_requested,
                // Factuur in WeFact (null = nog geen); invoice_defaults = gegevens van de reservatie om voor te vullen
                'invoice' => $order->invoice?->toFrontdeskArray(),
                'invoice_defaults' => $order->invoice ? null : OrderInvoiceService::detailsFromRegistration($order),
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
                    'id' => $order->creator->id,
                    'name' => $order->creator->name,
                ] : null,

                'updater' => $order->updater ? [
                    'id' => $order->updater->id,
                    'name' => $order->updater->name,
                ] : null,

                'payer' => $order->payer ? [
                    'id' => $order->payer->id,
                    'name' => $order->payer->name,
                ] : null,

                'canceller' => $order->canceller ? [
                    'id' => $order->canceller->id,
                    'name' => $order->canceller->name,
                ] : null,

                'refunder' => $order->refunder ? [
                    'id' => $order->refunder->id,
                    'name' => $order->refunder->name,
                ] : null,

                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => (int) $item->quantity,
                    'line_total_incl_vat' => (float) $item->line_total_incl_vat,
                    'source' => $item->source,
                    'source_reference' => $item->source_reference,
                    'creator' => $item->creator ? [
                        'id' => $item->creator->id,
                        'name' => $item->creator->name,
                    ] : null,
                    'updater' => $item->updater ? [
                        'id' => $item->updater->id,
                        'name' => $item->updater->name,
                    ] : null,
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

    /** Openstaande facturen (alle dagen): aantal, bedrag en hoeveel vervallen. */
    private function openInvoiceSummary(int $tenantId): array
    {
        $open = OrderInvoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('payment_status', OrderInvoiceService::PAY_UNSETTLED)
            ->get(['payment_status', 'amount_outstanding']);

        return [
            'count'   => $open->count(),
            'amount'  => round((float) $open->sum('amount_outstanding'), 2),
            'overdue' => $open->where('payment_status', OrderInvoiceService::PAY_OVERDUE)->count(),
        ];
    }
}

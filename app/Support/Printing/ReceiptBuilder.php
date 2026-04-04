<?php

namespace App\Support\Printing;

use App\Models\Order;

class ReceiptBuilder
{
    public static function build(Order $order, ?array $tenant = null): array
    {
        $order->loadMissing(['items.product', 'registration']);

        $tenantName = $tenant['name'] ?? config('app.name', 'Playdrive');
        $footer = $tenant['footer'] ?? 'Bedankt en tot snel!';
        $address = $tenant['address'] ?? null;
        $phone = $tenant['phone'] ?? null;
        $vat = $tenant['vat'] ?? null;

        $lines = $order->items
            ->sortBy('sort_order')
            ->values()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price_incl_vat,
                    'total' => (float) $item->line_total_incl_vat,
                ];
            })
            ->all();

        return [
            'meta' => [
                'tenant_name' => $tenantName,
                'address' => $address,
                'phone' => $phone,
                'vat' => $vat,
                'footer' => $footer,
            ],
            'order' => [
                'id' => $order->id,
                'paid_at' => optional($order->paid_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'payment_method' => self::formatPaymentMethod($order->payment_method),
                'invoice_requested' => (bool) $order->invoice_requested,
                'notes' => $order->notes,
                'total' => (float) $order->total_incl_vat,
                'subtotal' => (float) $order->subtotal_excl_vat,
                'vat' => (float) $order->total_vat,
                'source' => $order->source,
            ],
            'registration' => $order->registration ? [
                'id' => $order->registration->id,
                'name' => $order->registration->name,
            ] : null,
            'lines' => $lines,
        ];
    }

    protected static function formatPaymentMethod(?string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'bancontact' => 'Bancontact',
            'card' => 'Kaart',
            default => ucfirst((string) ($method ?: 'Onbekend')),
        };
    }
}

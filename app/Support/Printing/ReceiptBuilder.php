<?php

namespace App\Support\Printing;

use App\Models\Order;
use App\Models\Tenant;

class ReceiptBuilder
{
    public static function build(Order $order, Tenant|array|null $tenant = null): array
    {
        $order->loadMissing(['items.product', 'registration']);

        $meta = self::resolveTenantMeta($tenant);

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
            'meta' => $meta,
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

    protected static function resolveTenantMeta(Tenant|array|null $tenant): array
    {
        if ($tenant instanceof Tenant) {
            return [
                'tenant_name' => $tenant->display_name,
                'address' => $tenant->full_address,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'vat' => $tenant->vat_number,
                'logo_url' => $tenant->logo_url,
                'footer' => $tenant->receipt_footer ?: 'Bedankt en tot snel!',
            ];
        }

        return [
            'tenant_name' => $tenant['name'] ?? config('app.name', 'Playdrive'),
            'address' => $tenant['address'] ?? null,
            'phone' => $tenant['phone'] ?? null,
            'email' => $tenant['email'] ?? null,
            'vat' => $tenant['vat'] ?? null,
            'logo_url' => $tenant['logo_url'] ?? null,
            'footer' => $tenant['footer'] ?? 'Bedankt en tot snel!',
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

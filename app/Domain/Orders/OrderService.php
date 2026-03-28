<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function checkout(array $payload, CurrentTenant $currentTenant): Order
    {
        if (! $currentTenant->exists()) {
            throw new InvalidArgumentException('Geen tenant gevonden voor deze checkout.');
        }

        $itemsPayload = collect(Arr::get($payload, 'items', []))
            ->filter(fn ($item) => filled($item['product_id'] ?? null) && (int) ($item['quantity'] ?? 0) > 0)
            ->values();

        $registrationId = Arr::get($payload, 'reservation_id');
        $registration = $registrationId ? Registration::query()->findOrFail($registrationId) : null;

        if ($itemsPayload->isEmpty()) {
            throw new InvalidArgumentException('Er zijn geen geldige orderlijnen om af te rekenen.');
        }

        $paymentMethod = (string) Arr::get($payload, 'payment_method', 'cash');
        $notes = Arr::get($payload, 'notes');
        $source = $registration ? Order::SOURCE_RESERVATION : Order::SOURCE_WALK_IN;

        $productIds = $itemsPayload
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw new InvalidArgumentException('Niet alle producten van deze bestelling zijn geldig voor de huidige tenant.');
        }

        return DB::transaction(function () use ($payload, $currentTenant, $registration, $paymentMethod, $notes, $source, $itemsPayload, $products) {
            $calculatedItems = [];
            $subtotalExclVat = 0.0;
            $totalVat = 0.0;
            $totalInclVat = 0.0;

            foreach ($itemsPayload->values() as $index => $itemPayload) {
                /** @var Product $product */
                $product = $products->get((int) $itemPayload['product_id']);
                $quantity = max(1, (int) ($itemPayload['quantity'] ?? 1));

                $unitPriceExclVat = round((float) $product->price_excl_vat, 2);
                $unitPriceInclVat = round((float) $product->price_incl_vat, 2);
                $vatRate = round((float) $product->vat_rate, 2);

                $lineSubtotalExclVat = round($unitPriceExclVat * $quantity, 2);
                $lineTotalInclVat = round($unitPriceInclVat * $quantity, 2);
                $lineVat = round($lineTotalInclVat - $lineSubtotalExclVat, 2);

                $subtotalExclVat += $lineSubtotalExclVat;
                $totalVat += $lineVat;
                $totalInclVat += $lineTotalInclVat;

                $calculatedItems[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_excl_vat' => $unitPriceExclVat,
                    'unit_price_incl_vat' => $unitPriceInclVat,
                    'vat_rate' => $vatRate,
                    'line_subtotal_excl_vat' => $lineSubtotalExclVat,
                    'line_vat' => $lineVat,
                    'line_total_incl_vat' => $lineTotalInclVat,
                    'sort_order' => $index + 1,
                ];
            }

            $order = Order::create([
                'tenant_id' => $currentTenant->id(),
                'registration_id' => $registration?->id,
                'status' => Order::STATUS_PAID,
                'source' => $source,
                'subtotal_excl_vat' => round($subtotalExclVat, 2),
                'total_vat' => round($totalVat, 2),
                'total_incl_vat' => round($totalInclVat, 2),
                'payment_method' => $paymentMethod,
                'paid_at' => now(),
                'created_by' => Auth::id(),
                'notes' => filled($notes) ? (string) $notes : null,
                'invoice_requested' => (bool) ($payload['invoice_requested'] ?? false),
            ]);

            $order->items()->createMany($calculatedItems);

            if ($registration) {
                $existingTotal = round(((int) $registration->bill_total_cents) / 100, 2);
                $newTotal = round($existingTotal + (float) $order->total_incl_vat, 2);

                $registration->update([
                    'status' => Registration::STATUS_PAID,
                    'bill_total_cents' => (int) round($newTotal * 100),
                ]);
            }

            return $order->load('items', 'registration');
        });
    }
}

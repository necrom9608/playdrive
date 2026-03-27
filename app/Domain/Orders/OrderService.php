<?php

namespace App\Domain\Orders;

use App\Models\CateringOptionProduct;
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

        if ($registration && (int) $registration->catering_option_id > 0) {
            $itemsPayload = $this->mergeAutomaticCateringItemsIntoPayload(
                $itemsPayload,
                $registration,
                $currentTenant
            );
        }

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

    public function applyAutomaticCateringItemsForRegistration(Registration $registration, CurrentTenant $currentTenant): void
    {
        if (! $registration->catering_option_id) {
            return;
        }

        $order = Order::query()->firstOrCreate(
            [
                'tenant_id' => $currentTenant->id(),
                'registration_id' => $registration->id,
                'status' => Order::STATUS_OPEN,
            ],
            [
                'source' => Order::SOURCE_RESERVATION,
                'subtotal_excl_vat' => 0,
                'total_discount_excl_vat' => 0,
                'total_vat' => 0,
                'total_incl_vat' => 0,
                'created_by' => Auth::id(),
            ]
        );

        $childrenCount = max(0, (int) ($registration->participants_children ?? 0));
        $adultCount = max(0, (int) ($registration->participants_adults ?? 0));

        $links = CateringOptionProduct::query()
            ->with('product')
            ->where('tenant_id', $currentTenant->id())
            ->where('catering_option_id', $registration->catering_option_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($links->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $links, $childrenCount, $adultCount) {
            foreach ($links as $link) {
                $product = $link->product;

                if (! $product) {
                    continue;
                }

                $quantity = 0;

                if ($link->applies_to_children) {
                    $quantity += $childrenCount;
                }

                if ($link->applies_to_adults) {
                    $quantity += $adultCount;
                }

                $quantity = (int) round($quantity * (float) $link->quantity_per_person);

                if ($quantity <= 0) {
                    continue;
                }

                $existingItem = $order->items()
                    ->where('product_id', $product->id)
                    ->first();

                if ($existingItem) {
                    continue;
                }

                $unitPriceExclVat = round((float) $product->price_excl_vat, 2);
                $unitPriceInclVat = round((float) $product->price_incl_vat, 2);
                $vatRate = round((float) $product->vat_rate, 2);

                $lineSubtotalExclVat = round($unitPriceExclVat * $quantity, 2);
                $lineTotalInclVat = round($unitPriceInclVat * $quantity, 2);
                $lineVat = round($lineTotalInclVat - $lineSubtotalExclVat, 2);

                $nextSortOrder = ((int) $order->items()->max('sort_order')) + 1;

                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_excl_vat' => $unitPriceExclVat,
                    'unit_price_incl_vat' => $unitPriceInclVat,
                    'vat_rate' => $vatRate,
                    'line_subtotal_excl_vat' => $lineSubtotalExclVat,
                    'line_vat' => $lineVat,
                    'line_total_incl_vat' => $lineTotalInclVat,
                    'sort_order' => $nextSortOrder,
                ]);
            }

            $items = $order->items()->get();

            $order->update([
                'subtotal_excl_vat' => round((float) $items->sum('line_subtotal_excl_vat'), 2),
                'total_discount_excl_vat' => 0,
                'total_vat' => round((float) $items->sum('line_vat'), 2),
                'total_incl_vat' => round((float) $items->sum('line_total_incl_vat'), 2),
            ]);
        });
    }

    private function mergeAutomaticCateringItemsIntoPayload($itemsPayload, Registration $registration, CurrentTenant $currentTenant)
    {
        $childrenCount = max(0, (int) $registration->participants_children);
        $adultCount = max(0, (int) $registration->participants_adults);

        $automaticLinks = CateringOptionProduct::query()
            ->with('product')
            ->where('tenant_id', $currentTenant->id())
            ->where('catering_option_id', $registration->catering_option_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($automaticLinks->isEmpty()) {
            return $itemsPayload;
        }

        $merged = $itemsPayload
            ->map(function ($item) {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];
            })
            ->keyBy('product_id');

        foreach ($automaticLinks as $link) {
            if (! $link->product) {
                continue;
            }

            $quantity = 0;

            if ($link->applies_to_children) {
                $quantity += $childrenCount;
            }

            if ($link->applies_to_adults) {
                $quantity += $adultCount;
            }

            $quantity = (int) round($quantity * (float) $link->quantity_per_person);

            if ($quantity <= 0) {
                continue;
            }

            $productId = (int) $link->product_id;

            if ($merged->has($productId)) {
                $existing = $merged->get($productId);
                $existing['quantity'] += $quantity;
                $merged->put($productId, $existing);
            } else {
                $merged->put($productId, [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);
            }
        }

        return $merged
            ->values()
            ->filter(fn ($item) => $item['product_id'] > 0 && $item['quantity'] > 0);
    }
}

<?php

namespace App\Domain\Orders;

use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\PricingEvaluator;
use App\Models\GiftVoucher;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        protected PricingEvaluator $pricingEvaluator,
    ) {
    }

    public function addManualItem(array $payload, CurrentTenant $currentTenant): Order
    {
        if (! $currentTenant->exists()) {
            throw new InvalidArgumentException('Geen tenant gevonden voor deze actie.');
        }

        $product = Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail((int) $payload['product_id']);

        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $registrationId = Arr::get($payload, 'registration_id', Arr::get($payload, 'reservation_id'));
        $registration = $this->resolveRegistration($registrationId, $currentTenant);
        $source = $registration ? Order::SOURCE_RESERVATION : Order::SOURCE_WALK_IN;
        $actorUserId = $this->frontdeskUserId();

        return DB::transaction(function () use ($currentTenant, $registration, $source, $product, $quantity, $actorUserId) {
            $order = $this->findOrCreateOpenOrder($currentTenant, $registration, $source, $actorUserId);

            $lastManualItem = $order->items()
                ->where('source', 'manual')
                ->whereNull('source_reference')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->last();

            if ($lastManualItem && (int) $lastManualItem->product_id === (int) $product->id) {
                $this->applyQuantityToItem(
                    $lastManualItem,
                    (int) $lastManualItem->quantity + $quantity,
                    $actorUserId
                );
            } else {
                $nextSortOrder = ((int) ($order->items()->max('sort_order') ?? 0)) + 1;

                $this->createOrderItem(
                    $order,
                    $product,
                    $quantity,
                    'manual',
                    null,
                    $actorUserId,
                    $nextSortOrder,
                );
            }

            $this->recalculateOrderTotals($order, $actorUserId);
            $this->syncRegistrationBillTotal($order, $actorUserId);

            return $this->freshOrder($order);
        });
    }

    public function updateOrderItemQuantity(Order $order, OrderItem $item, int $quantity, CurrentTenant $currentTenant): Order
    {
        $this->assertMutableOrderItem($order, $item, $currentTenant);
        $actorUserId = $this->frontdeskUserId();

        return DB::transaction(function () use ($order, $item, $quantity, $actorUserId) {
            $this->applyQuantityToItem($item, max(1, $quantity), $actorUserId);
            $this->recalculateOrderTotals($order, $actorUserId);
            $this->syncRegistrationBillTotal($order, $actorUserId);

            return $this->freshOrder($order);
        });
    }

    public function removeOrderItem(Order $order, OrderItem $item, CurrentTenant $currentTenant): Order
    {
        $this->assertMutableOrderItem($order, $item, $currentTenant);
        $actorUserId = $this->frontdeskUserId();

        return DB::transaction(function () use ($order, $item, $actorUserId) {
            $item->delete();
            $this->normalizeSortOrders($order);
            $this->recalculateOrderTotals($order, $actorUserId);
            $this->syncRegistrationBillTotal($order, $actorUserId);

            return $this->freshOrder($order);
        });
    }

    public function checkout(array $payload, CurrentTenant $currentTenant): Order
    {
        if (! $currentTenant->exists()) {
            throw new InvalidArgumentException('Geen tenant gevonden voor deze checkout.');
        }

        $actorUserId = $this->frontdeskUserId();
        $registrationId = Arr::get($payload, 'registration_id');
        $registration = $this->resolveRegistration($registrationId, $currentTenant);
        $source = $registration ? Order::SOURCE_RESERVATION : Order::SOURCE_WALK_IN;

        $order = null;
        $orderId = Arr::get($payload, 'order_id');

        if ($orderId) {
            $order = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('status', Order::STATUS_OPEN)
                ->findOrFail((int) $orderId);
        } elseif ($registration) {
            $order = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('registration_id', $registration->id)
                ->where('status', Order::STATUS_OPEN)
                ->latest('id')
                ->first();
        } else {
            $order = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->whereNull('registration_id')
                ->where('source', Order::SOURCE_WALK_IN)
                ->where('status', Order::STATUS_OPEN)
                ->latest('id')
                ->first();
        }

        if (! $order) {
            $itemsPayload = collect(Arr::get($payload, 'items', []))
                ->filter(fn ($item) => filled($item['product_id'] ?? null) && (int) ($item['quantity'] ?? 0) > 0)
                ->values();

            if ($itemsPayload->isEmpty()) {
                throw new InvalidArgumentException('Er zijn geen geldige orderlijnen om af te rekenen.');
            }

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

            $paymentMethod = (string) Arr::get($payload, 'payment_method', 'cash');
            $notes = Arr::get($payload, 'notes');

            return DB::transaction(function () use ($payload, $currentTenant, $registration, $paymentMethod, $notes, $source, $itemsPayload, $products, $actorUserId) {
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
                        'source' => 'manual',
                        'source_reference' => null,
                        'created_by' => $actorUserId,
                        'updated_by' => $actorUserId,
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
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                    'paid_by' => $actorUserId,
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
                        'updated_by' => $actorUserId,
                    ]);
                }

                return $order->load(['items.product', 'registration']);
            });
        }

        if ((int) $order->tenant_id !== (int) $currentTenant->id()) {
            throw new InvalidArgumentException('Order hoort niet bij de huidige tenant.');
        }

        if ($order->status !== Order::STATUS_OPEN) {
            throw new InvalidArgumentException('Alleen open orders kunnen afgerekend worden.');
        }

        if ($registration && (int) $order->registration_id !== (int) $registration->id) {
            throw new InvalidArgumentException('Order hoort niet bij de geselecteerde registratie.');
        }

        if (! $registration && $order->registration_id) {
            throw new InvalidArgumentException('Dit order hoort bij een registratie en kan niet als losse verkoop afgerekend worden.');
        }

        if (! $order->items()->exists()) {
            throw new InvalidArgumentException('Er zijn geen geldige orderlijnen om af te rekenen.');
        }

        return DB::transaction(function () use ($order, $payload, $registration, $actorUserId) {
            $this->recalculateOrderTotals($order, $actorUserId);

            $order->update([
                'status' => Order::STATUS_PAID,
                'payment_method' => (string) Arr::get($payload, 'payment_method', 'cash'),
                'paid_at' => now(),
                'paid_by' => $actorUserId,
                'updated_by' => $actorUserId,
                'notes' => filled(Arr::get($payload, 'notes')) ? (string) Arr::get($payload, 'notes') : null,
                'invoice_requested' => (bool) Arr::get($payload, 'invoice_requested', false),
            ]);

            GiftVoucher::query()
                ->where('applied_order_id', $order->id)
                ->where('status', GiftVoucher::STATUS_VALIDATED)
                ->get()
                ->each(function (GiftVoucher $voucher) use ($actorUserId) {
                    $voucher->update([
                        'status' => GiftVoucher::STATUS_REDEEMED,
                        'amount_remaining' => 0,
                        'redeemed_at' => now(),
                        'redeemed_by' => $actorUserId,
                        'updated_by' => $actorUserId,
                    ]);
                });

            if ($registration) {
                $registration->update([
                    'status' => Registration::STATUS_PAID,
                    'bill_total_cents' => (int) round(((float) $order->fresh()->total_incl_vat) * 100),
                    'updated_by' => $actorUserId,
                ]);
            }

            return $order->fresh(['items.product', 'registration']);
        });
    }

    public function syncPricingForRegistration(Registration $registration, CurrentTenant $currentTenant): Order
    {
        if (! $currentTenant->exists()) {
            throw new InvalidArgumentException('Geen tenant gevonden voor deze actie.');
        }

        if ((int) $registration->tenant_id !== (int) $currentTenant->id()) {
            throw new InvalidArgumentException('Registratie hoort niet bij de huidige tenant.');
        }

        $pricingResult = $this->pricingEvaluator->evaluate(
            PricingContext::fromRegistration($registration)
        );

        $productIds = collect($pricingResult->lines ?? [])
            ->map(fn ($line) => (int) ($line->productId ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $products = Product::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw new InvalidArgumentException('Niet alle producten uit de prijsregels zijn geldig voor de huidige tenant.');
        }

        $actorUserId = $this->frontdeskUserId();

        return DB::transaction(function () use ($registration, $currentTenant, $pricingResult, $products, $actorUserId) {
            $order = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('registration_id', $registration->id)
                ->where('status', Order::STATUS_OPEN)
                ->latest('id')
                ->first();

            if (! $order) {
                $order = Order::create([
                    'tenant_id' => $currentTenant->id(),
                    'registration_id' => $registration->id,
                    'status' => Order::STATUS_OPEN,
                    'source' => Order::SOURCE_RESERVATION,
                    'subtotal_excl_vat' => 0,
                    'total_vat' => 0,
                    'total_incl_vat' => 0,
                    'payment_method' => null,
                    'paid_at' => null,
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                    'paid_by' => null,
                    'notes' => null,
                    'invoice_requested' => (bool) $registration->invoice_requested,
                ]);
            }

            $sourceReference = 'registration:' . $registration->id;

            $order->items()
                ->where('source', 'pricing_engine')
                ->where('source_reference', $sourceReference)
                ->delete();

            $nextSortOrder = (int) ($order->items()->max('sort_order') ?? 0);

            foreach (collect($pricingResult->lines ?? [])->values() as $index => $line) {
                /** @var Product|null $product */
                $product = $products->get((int) ($line->productId ?? 0));

                if (! $product) {
                    continue;
                }

                $this->createOrderItem(
                    $order,
                    $product,
                    max(1, (int) ($line->quantity ?? 1)),
                    'pricing_engine',
                    $sourceReference,
                    $actorUserId,
                    $nextSortOrder + $index + 1,
                );
            }

            $this->recalculateOrderTotals($order, $actorUserId);

            $registration->update([
                'bill_total_cents' => (int) round(((float) $order->total_incl_vat) * 100),
                'updated_by' => $actorUserId,
            ]);

            return $order->load(['items.product', 'registration']);
        });
    }

    protected function resolveRegistration(mixed $registrationId, CurrentTenant $currentTenant): ?Registration
    {
        if (! $registrationId) {
            return null;
        }

        return Registration::query()
            ->where('tenant_id', $currentTenant->id())
            ->findOrFail((int) $registrationId);
    }

    protected function findOrCreateOpenOrder(CurrentTenant $currentTenant, ?Registration $registration, string $source, ?int $actorUserId): Order
    {
        $query = Order::query()
            ->where('tenant_id', $currentTenant->id())
            ->where('status', Order::STATUS_OPEN);

        if ($registration) {
            $query->where('registration_id', $registration->id);
        } else {
            $query->whereNull('registration_id')
                ->where('source', Order::SOURCE_WALK_IN);
        }

        $order = $query->latest('id')->first();

        if ($order) {
            return $order;
        }

        return Order::create([
            'tenant_id' => $currentTenant->id(),
            'registration_id' => $registration?->id,
            'status' => Order::STATUS_OPEN,
            'source' => $source,
            'subtotal_excl_vat' => 0,
            'total_vat' => 0,
            'total_incl_vat' => 0,
            'payment_method' => null,
            'invoice_requested' => (bool) ($registration?->invoice_requested ?? false),
            'paid_at' => null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
            'paid_by' => null,
            'notes' => null,
        ]);
    }

    protected function assertMutableOrderItem(Order $order, OrderItem $item, CurrentTenant $currentTenant): void
    {
        if (! $currentTenant->exists() || (int) $order->tenant_id !== (int) $currentTenant->id()) {
            throw new InvalidArgumentException('Order hoort niet bij de huidige tenant.');
        }

        if ((int) $item->order_id !== (int) $order->id) {
            throw new InvalidArgumentException('Orderlijn hoort niet bij dit order.');
        }

        if ($order->status !== Order::STATUS_OPEN) {
            throw new InvalidArgumentException('Alleen open orders kunnen aangepast worden.');
        }
    }

    protected function createOrderItem(Order $order, Product $product, int $quantity, string $source, ?string $sourceReference, ?int $actorUserId, int $sortOrder): OrderItem
    {
        $quantity = max(1, $quantity);
        $unitPriceExclVat = round((float) $product->price_excl_vat, 2);
        $unitPriceInclVat = round((float) $product->price_incl_vat, 2);
        $vatRate = round((float) $product->vat_rate, 2);
        $lineSubtotalExclVat = round($unitPriceExclVat * $quantity, 2);
        $lineTotalInclVat = round($unitPriceInclVat * $quantity, 2);
        $lineVat = round($lineTotalInclVat - $lineSubtotalExclVat, 2);

        return $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price_excl_vat' => $unitPriceExclVat,
            'unit_price_incl_vat' => $unitPriceInclVat,
            'vat_rate' => $vatRate,
            'line_subtotal_excl_vat' => $lineSubtotalExclVat,
            'line_vat' => $lineVat,
            'line_total_incl_vat' => $lineTotalInclVat,
            'sort_order' => $sortOrder,
            'source' => $source,
            'source_reference' => $sourceReference,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    protected function applyQuantityToItem(OrderItem $item, int $quantity, ?int $actorUserId): void
    {
        $quantity = max(1, $quantity);
        $unitPriceExclVat = round((float) $item->unit_price_excl_vat, 2);
        $unitPriceInclVat = round((float) $item->unit_price_incl_vat, 2);
        $lineSubtotalExclVat = round($unitPriceExclVat * $quantity, 2);
        $lineTotalInclVat = round($unitPriceInclVat * $quantity, 2);
        $lineVat = round($lineTotalInclVat - $lineSubtotalExclVat, 2);

        $item->update([
            'quantity' => $quantity,
            'line_subtotal_excl_vat' => $lineSubtotalExclVat,
            'line_total_incl_vat' => $lineTotalInclVat,
            'line_vat' => $lineVat,
            'updated_by' => $actorUserId,
        ]);
    }

    protected function normalizeSortOrders(Order $order): void
    {
        $order->items()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (OrderItem $item, int $index) {
                $item->update([
                    'sort_order' => $index + 1,
                ]);
            });
    }

    protected function recalculateOrderTotals(Order $order, ?int $actorUserId = null): void
    {
        $items = $order->items()->get();

        $subtotalExclVat = round((float) $items->sum(fn ($item) => (float) $item->line_subtotal_excl_vat), 2);
        $totalVat = round((float) $items->sum(fn ($item) => (float) $item->line_vat), 2);
        $totalInclVat = round((float) $items->sum(fn ($item) => (float) $item->line_total_incl_vat), 2);

        $order->update([
            'subtotal_excl_vat' => $subtotalExclVat,
            'total_vat' => $totalVat,
            'total_incl_vat' => $totalInclVat,
            'updated_by' => $actorUserId ?? $this->frontdeskUserId(),
        ]);
    }

    protected function syncRegistrationBillTotal(Order $order, ?int $actorUserId = null): void
    {
        if (! $order->registration_id) {
            return;
        }

        $registration = $order->registration()->first();

        if (! $registration) {
            return;
        }

        $registration->update([
            'bill_total_cents' => (int) round(((float) $order->fresh()->total_incl_vat) * 100),
            'updated_by' => $actorUserId ?? $this->frontdeskUserId(),
        ]);
    }

    protected function freshOrder(Order $order): Order
    {
        return $order->fresh([
            'items.product',
            'creator:id,name',
            'updater:id,name',
            'payer:id,name',
            'canceller:id,name',
            'refunder:id,name',
            'items.creator:id,name',
            'items.updater:id,name',
            'registration',
        ]);
    }

    protected function frontdeskUserId(): ?int
    {
        return request()?->attributes?->get('frontdesk_user')?->id;
    }
}

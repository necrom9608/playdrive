<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Orders\OrderService;
use App\Http\Controllers\Controller;
use App\Models\GiftVoucher;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GiftVoucherController extends Controller
{
    public function __construct(protected OrderService $orderService)
    {
    }

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();

        $query = GiftVoucher::query()->where('tenant_id', $currentTenant->id());

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('code', 'like', '%' . $search . '%')
                    ->orWhere('qr_token', 'like', '%' . $search . '%')
                    ->orWhere('nfc_uid', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_email', 'like', '%' . $search . '%');
            });
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $vouchers = $query->orderByDesc('id')->get();
        $baseQuery = GiftVoucher::query()->where('tenant_id', $currentTenant->id());

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => (clone $baseQuery)->count(),
                    'active' => (clone $baseQuery)->where('status', GiftVoucher::STATUS_ACTIVE)->count(),
                    'validated' => (clone $baseQuery)->where('status', GiftVoucher::STATUS_VALIDATED)->count(),
                    'redeemed' => (clone $baseQuery)->where('status', GiftVoucher::STATUS_REDEEMED)->count(),
                ],
                'vouchers' => $vouchers->map(fn (GiftVoucher $voucher) => $this->transformVoucher($voucher))->values(),
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $this->validatePayload($request, $currentTenant, null);
        $userId = $this->frontdeskUserId($request);

        $voucher = GiftVoucher::query()->create([
            'tenant_id' => $currentTenant->id(),
            'code' => $data['code'] ?: $this->generateVoucherCode(),
            'qr_token' => $this->nullableValue($data['qr_token'] ?? null),
            'nfc_uid' => $this->nullableValue($data['nfc_uid'] ?? null),
            'name' => $this->nullableValue($data['name'] ?? null),
            'customer_name' => $this->nullableValue($data['customer_name'] ?? null),
            'customer_email' => $this->nullableValue($data['customer_email'] ?? null),
            'source_channel' => $data['source_channel'] ?? 'frontdesk',
            'status' => $data['status'] ?? GiftVoucher::STATUS_ACTIVE,
            'amount_initial' => $data['amount_initial'],
            'amount_remaining' => $data['amount_remaining'] ?? $data['amount_initial'],
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return response()->json(['data' => $this->transformVoucher($voucher)], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, GiftVoucher $voucher): JsonResponse
    {
        abort_unless((int) $voucher->tenant_id === (int) $currentTenant->id(), 404);
        $data = $this->validatePayload($request, $currentTenant, $voucher->id);
        $userId = $this->frontdeskUserId($request);

        $voucher->update([
            'code' => $data['code'],
            'qr_token' => $this->nullableValue($data['qr_token'] ?? null),
            'nfc_uid' => $this->nullableValue($data['nfc_uid'] ?? null),
            'name' => $this->nullableValue($data['name'] ?? null),
            'customer_name' => $this->nullableValue($data['customer_name'] ?? null),
            'customer_email' => $this->nullableValue($data['customer_email'] ?? null),
            'source_channel' => $data['source_channel'] ?? 'frontdesk',
            'status' => $data['status'] ?? $voucher->status,
            'amount_initial' => $data['amount_initial'],
            'amount_remaining' => $data['amount_remaining'] ?? $voucher->amount_remaining,
            'expires_at' => $data['expires_at'] ?? null,
            'updated_by' => $userId,
        ]);

        return response()->json(['data' => $this->transformVoucher($voucher->fresh())]);
    }

    public function validateForPos(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'reservation_id' => ['nullable', 'integer'],
        ]);

        $rawCode = trim($validated['code']);
        $userId = $this->frontdeskUserId($request);

        $voucher = GiftVoucher::query()
            ->where('tenant_id', $currentTenant->id())
            ->where(function (Builder $builder) use ($rawCode) {
                $builder->where('code', $rawCode)
                    ->orWhere('qr_token', $rawCode)
                    ->orWhere('nfc_uid', $rawCode);
            })
            ->first();

        if (!$voucher) {
            return response()->json(['message' => 'Cadeaubon niet gevonden.'], 404);
        }

        if ($voucher->status === GiftVoucher::STATUS_REDEEMED) {
            return response()->json(['message' => 'Deze cadeaubon werd al ingewisseld.'], 422);
        }

        if ($voucher->status === GiftVoucher::STATUS_CANCELLED || $voucher->status === GiftVoucher::STATUS_EXPIRED) {
            return response()->json(['message' => 'Deze cadeaubon is niet meer geldig.'], 422);
        }

        $amount = round((float) $voucher->amount_remaining, 2);
        if ($amount <= 0) {
            return response()->json(['message' => 'Deze cadeaubon heeft geen resterend saldo.'], 422);
        }

        try {
            $order = DB::transaction(function () use ($currentTenant, $validated, $voucher, $amount, $userId) {
                $registration = null;
                if (!empty($validated['reservation_id'])) {
                    $registration = \App\Models\Registration::query()
                        ->where('tenant_id', $currentTenant->id())
                        ->findOrFail((int) $validated['reservation_id']);
                }

                $source = $registration ? Order::SOURCE_RESERVATION : Order::SOURCE_WALK_IN;

                $order = Order::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('status', Order::STATUS_OPEN)
                    ->when($registration, fn ($query) => $query->where('registration_id', $registration->id), fn ($query) => $query->whereNull('registration_id')->where('source', Order::SOURCE_WALK_IN))
                    ->latest('id')
                    ->first();

                if (!$order) {
                    $order = Order::query()->create([
                        'tenant_id' => $currentTenant->id(),
                        'registration_id' => $registration?->id,
                        'status' => Order::STATUS_OPEN,
                        'source' => $source,
                        'subtotal_excl_vat' => 0,
                        'total_vat' => 0,
                        'total_incl_vat' => 0,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                $existing = $order->items()->where('source', 'voucher')->where('source_reference', (string) $voucher->id)->first();

                if (!$existing) {
                    $sortOrder = ((int) $order->items()->max('sort_order')) + 1;
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => null,
                        'name' => 'Cadeaubon ' . $voucher->code,
                        'quantity' => 1,
                        'unit_price_excl_vat' => -$amount,
                        'unit_price_incl_vat' => -$amount,
                        'vat_rate' => 0,
                        'line_subtotal_excl_vat' => -$amount,
                        'line_vat' => 0,
                        'line_total_incl_vat' => -$amount,
                        'sort_order' => $sortOrder,
                        'source' => 'voucher',
                        'source_reference' => (string) $voucher->id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                $voucher->update([
                    'status' => GiftVoucher::STATUS_VALIDATED,
                    'validated_at' => now(),
                    'validated_by' => $userId,
                    'applied_order_id' => $order->id,
                    'updated_by' => $userId,
                ]);

                $order->subtotal_excl_vat = (float) $order->items()->sum('line_subtotal_excl_vat');
                $order->total_vat = (float) $order->items()->sum('line_vat');
                $order->total_incl_vat = (float) $order->items()->sum('line_total_incl_vat');
                $order->updated_by = $userId;
                $order->save();

                return $order->fresh([
                    'items.product',
                    'creator:id,name','updater:id,name','payer:id,name','canceller:id,name','refunder:id,name',
                    'items.creator:id,name','items.updater:id,name',
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cadeaubon toegevoegd aan de bestelling.',
            'data' => [
                'voucher' => $this->transformVoucher($voucher->fresh()),
                'order' => app(OrderController::class)->transformOrder($order),
            ],
        ]);
    }

    protected function validatePayload(Request $request, CurrentTenant $currentTenant, ?int $voucherId): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:255', 'unique:gift_vouchers,code,' . ($voucherId ?? 'NULL') . ',id,tenant_id,' . $currentTenant->id()],
            'qr_token' => ['nullable', 'string', 'max:255', 'unique:gift_vouchers,qr_token,' . ($voucherId ?? 'NULL') . ',id,tenant_id,' . $currentTenant->id()],
            'nfc_uid' => ['nullable', 'string', 'max:255', 'unique:gift_vouchers,nfc_uid,' . ($voucherId ?? 'NULL') . ',id,tenant_id,' . $currentTenant->id()],
            'name' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'source_channel' => ['nullable', 'in:frontdesk,website'],
            'status' => ['nullable', 'in:active,validated,redeemed,cancelled,expired'],
            'amount_initial' => ['required', 'numeric', 'min:0.01'],
            'amount_remaining' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }

    protected function transformVoucher(GiftVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'qr_token' => $voucher->qr_token,
            'nfc_uid' => $voucher->nfc_uid,
            'name' => $voucher->name,
            'customer_name' => $voucher->customer_name,
            'customer_email' => $voucher->customer_email,
            'source_channel' => $voucher->source_channel,
            'source_channel_label' => $voucher->source_channel === 'website' ? 'Website / QR' : 'Frontdesk / NFC',
            'status' => $voucher->status,
            'status_label' => GiftVoucher::statusOptions()[$voucher->status] ?? $voucher->status,
            'amount_initial' => (float) $voucher->amount_initial,
            'amount_remaining' => (float) $voucher->amount_remaining,
            'expires_at' => optional($voucher->expires_at)->format('Y-m-d'),
            'expires_at_label' => optional($voucher->expires_at)->format('d/m/Y'),
            'validated_at' => optional($voucher->validated_at)->toIso8601String(),
            'redeemed_at' => optional($voucher->redeemed_at)->toIso8601String(),
        ];
    }

    protected function frontdeskUserId(Request $request): ?int
    {
        return $request->attributes->get('frontdesk_user')?->id;
    }

    protected function nullableValue(mixed $value): mixed
    {
        return filled($value) ? $value : null;
    }

    protected function generateVoucherCode(): string
    {
        return 'BON-' . Str::upper(Str::random(10));
    }
}

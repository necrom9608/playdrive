<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCard;
use App\Models\VoucherTemplate;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PhysicalCardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();
        $voucherTemplateId = $request->integer('voucher_template_id') ?: null;

        $query = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->with([
                'voucherTemplate:id,name,product_id,badge_template_id',
                'voucherTemplate.product:id,name,description,price_excl_vat,vat_rate',
                'voucherTemplate.badgeTemplate:id,name,config_json',
                'currentGiftVoucher:id,code,status',
                'lastGiftVoucher:id,code,status',
            ]);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('rfid_uid', 'like', '%' . $search . '%')
                    ->orWhere('label', 'like', '%' . $search . '%')
                    ->orWhere('internal_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('voucherTemplate', function (Builder $templateQuery) use ($search) {
                        $templateQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($voucherTemplateId) {
            $query->where('voucher_template_id', $voucherTemplateId);
        }

        $cards = $query->orderByDesc('id')->get();
        $baseQuery = PhysicalCard::query()->where('tenant_id', $currentTenant->id());

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => (clone $baseQuery)->count(),
                    'stock' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_STOCK)->count(),
                    'in_circulation' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_IN_CIRCULATION)->count(),
                    'returned' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_RETURNED)->count(),
                    'blocked' => (clone $baseQuery)->whereIn('status', [PhysicalCard::STATUS_BLOCKED, PhysicalCard::STATUS_RETIRED])->count(),
                ],
                'voucher_templates' => VoucherTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('is_active', true)
                    ->with(['product:id,name,description,price_excl_vat,vat_rate', 'badgeTemplate:id,name'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (VoucherTemplate $template) => $this->transformVoucherTemplate($template))
                    ->values(),
                'cards' => $cards->map(fn (PhysicalCard $card) => $this->transformCard($card))->values(),
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $this->validatePayload($request, $currentTenant, null);
        $userId = $this->frontdeskUserId($request);

        $card = PhysicalCard::query()->create([
            'tenant_id' => $currentTenant->id(),
            'voucher_template_id' => $data['voucher_template_id'],
            'label' => $this->nullableValue($data['label'] ?? null),
            'internal_reference' => $this->nullableValue($data['internal_reference'] ?? null),
            'rfid_uid' => trim($data['rfid_uid']),
            'status' => $data['status'] ?? PhysicalCard::STATUS_STOCK,
            'notes' => $this->nullableValue($data['notes'] ?? null),
            'printed_at' => ! empty($data['printed_at']) ? $data['printed_at'] : null,
            'issued_at' => ! empty($data['issued_at']) ? $data['issued_at'] : null,
            'returned_at' => ! empty($data['returned_at']) ? $data['returned_at'] : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $card->load($this->detailRelations());

        return response()->json(['data' => $this->transformCard($card)], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $data = $this->validatePayload($request, $currentTenant, $card->id);
        $userId = $this->frontdeskUserId($request);

        $card->update([
            'voucher_template_id' => $data['voucher_template_id'],
            'label' => $this->nullableValue($data['label'] ?? null),
            'internal_reference' => $this->nullableValue($data['internal_reference'] ?? null),
            'rfid_uid' => trim($data['rfid_uid']),
            'status' => $data['status'] ?? $card->status,
            'notes' => $this->nullableValue($data['notes'] ?? null),
            'printed_at' => ! empty($data['printed_at']) ? $data['printed_at'] : null,
            'issued_at' => ! empty($data['issued_at']) ? $data['issued_at'] : null,
            'returned_at' => ! empty($data['returned_at']) ? $data['returned_at'] : null,
            'updated_by' => $userId,
        ]);

        $card->load($this->detailRelations());

        return response()->json(['data' => $this->transformCard($card)]);
    }

    public function markPrinted(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $card->forceFill([
            'printed_at' => Carbon::now(),
            'updated_by' => $this->frontdeskUserId($request),
        ])->save();

        $card->load($this->detailRelations());

        return response()->json([
            'data' => [
                'card' => $this->transformCard($card),
                'print_url' => url('/frontdesk/cards/' . $card->id . '/print'),
            ],
        ]);
    }

    protected function validatePayload(Request $request, CurrentTenant $currentTenant, ?int $cardId): array
    {
        $data = $request->validate([
            'voucher_template_id' => ['required', 'integer', 'exists:voucher_templates,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'internal_reference' => ['nullable', 'string', 'max:255'],
            'rfid_uid' => ['required', 'string', 'max:255', 'unique:physical_cards,rfid_uid,' . ($cardId ?? 'NULL') . ',id,tenant_id,' . $currentTenant->id()],
            'status' => ['nullable', 'in:' . implode(',', array_keys(PhysicalCard::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'printed_at' => ['nullable', 'date'],
            'issued_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
        ]);

        abort_unless(
            VoucherTemplate::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('id', (int) $data['voucher_template_id'])
                ->exists(),
            422,
            'Ongeldig voucher type geselecteerd.'
        );

        return $data;
    }

    protected function transformVoucherTemplate(VoucherTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'product_name' => $template->product?->name,
            'product_price_incl_vat' => $template->product?->price_incl_vat,
            'badge_template_name' => $template->badgeTemplate?->name,
        ];
    }

    protected function transformCard(PhysicalCard $card): array
    {
        return [
            'id' => $card->id,
            'voucher_template_id' => $card->voucher_template_id,
            'voucher_template_name' => $card->voucherTemplate?->name,
            'product_name' => $card->voucherTemplate?->product?->name,
            'product_price_incl_vat' => $card->voucherTemplate?->product?->price_incl_vat,
            'badge_template_name' => $card->voucherTemplate?->badgeTemplate?->name,
            'label' => $card->label,
            'internal_reference' => $card->internal_reference,
            'rfid_uid' => $card->rfid_uid,
            'status' => $card->status,
            'status_label' => PhysicalCard::statusOptions()[$card->status] ?? $card->status,
            'notes' => $card->notes,
            'printed_at' => optional($card->printed_at)->format('Y-m-d'),
            'printed_at_label' => optional($card->printed_at)->format('d/m/Y'),
            'issued_at' => optional($card->issued_at)->format('Y-m-d'),
            'issued_at_label' => optional($card->issued_at)->format('d/m/Y'),
            'returned_at' => optional($card->returned_at)->format('Y-m-d'),
            'returned_at_label' => optional($card->returned_at)->format('d/m/Y'),
            'current_voucher_id' => $card->currentGiftVoucher?->id,
            'current_voucher_code' => $card->currentGiftVoucher?->code,
            'last_voucher_id' => $card->lastGiftVoucher?->id,
            'last_voucher_code' => $card->lastGiftVoucher?->code,
            'print_url' => url('/frontdesk/cards/' . $card->id . '/print'),
            'updated_at' => optional($card->updated_at)->toIso8601String(),
            'updated_at_label' => optional($card->updated_at)->format('d/m/Y H:i'),
        ];
    }

    protected function detailRelations(): array
    {
        return [
            'voucherTemplate:id,name,product_id,badge_template_id',
            'voucherTemplate.product:id,name,description,price_excl_vat,vat_rate',
            'voucherTemplate.badgeTemplate:id,name,config_json',
            'currentGiftVoucher:id,code,status',
            'lastGiftVoucher:id,code,status',
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
}

<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BadgeTemplate;
use App\Models\Member;
use App\Models\PhysicalCard;
use App\Models\User;
use App\Models\VoucherTemplate;
use App\Services\SimpleImagePdfService;
use App\Support\CurrentTenant;
use App\Support\PhysicalCardRenderData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PhysicalCardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $statuses = collect($request->input('statuses', []))->filter()->values()->all();
        $cardType = $this->nullableValue($request->input('card_type'));
        $voucherTemplateId = $request->integer('voucher_template_id') ?: null;
        $badgeTemplateId = $request->integer('badge_template_id') ?: null;

        $query = PhysicalCard::query()
            ->where('tenant_id', $currentTenant->id())
            ->with($this->detailRelations());

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('rfid_uid', 'like', '%' . $search . '%')
                    ->orWhere('label', 'like', '%' . $search . '%')
                    ->orWhere('internal_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('voucherTemplate', fn (Builder $templateQuery) => $templateQuery->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('badgeTemplate', fn (Builder $templateQuery) => $templateQuery->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('staffHolder', function (Builder $holderQuery) use ($search) {
                        $holderQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('memberHolder', function (Builder $holderQuery) use ($search) {
                        $holderQuery
                            ->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ['%' . $search . '%'])
                            ->orWhere('login', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if ($cardType) {
            $query->where('card_type', $cardType);
        }

        if ($voucherTemplateId) {
            $query->where('voucher_template_id', $voucherTemplateId);
        }

        if ($badgeTemplateId) {
            $query->where('badge_template_id', $badgeTemplateId);
        }

        $cards = $query->orderByDesc('id')->get();
        $baseQuery = PhysicalCard::query()->where('tenant_id', $currentTenant->id());

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => (clone $baseQuery)->count(),
                    'voucher' => (clone $baseQuery)->where('card_type', PhysicalCard::TYPE_VOUCHER)->count(),
                    'staff' => (clone $baseQuery)->where('card_type', PhysicalCard::TYPE_STAFF)->count(),
                    'member' => (clone $baseQuery)->where('card_type', PhysicalCard::TYPE_MEMBER)->count(),
                    'stock' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_STOCK)->count(),
                    'in_circulation' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_IN_CIRCULATION)->count(),
                    'returned' => (clone $baseQuery)->where('status', PhysicalCard::STATUS_RETURNED)->count(),
                    'blocked' => (clone $baseQuery)->whereIn('status', [PhysicalCard::STATUS_BLOCKED, PhysicalCard::STATUS_RETIRED])->count(),
                ],
                'card_types' => collect(PhysicalCard::typeOptions())
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
                'voucher_templates' => VoucherTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('is_active', true)
                    ->with(['product:id,name,description,price_excl_vat,vat_rate', 'badgeTemplate:id,name,template_type'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (VoucherTemplate $template) => $this->transformVoucherTemplate($template))
                    ->values(),
                'badge_templates' => BadgeTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->whereIn('template_type', [PhysicalCard::TYPE_STAFF, PhysicalCard::TYPE_MEMBER])
                    ->orderBy('template_type')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (BadgeTemplate $template) => $this->transformBadgeTemplate($template))
                    ->values(),
                'staff' => User::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->orderBy('name')
                    ->get(['id', 'name', 'username', 'email', 'rfid_uid'])
                    ->map(fn (User $user) => $this->transformStaffOption($user))
                    ->values(),
                'members' => Member::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'login', 'email', 'rfid_uid'])
                    ->map(fn (Member $member) => $this->transformMemberOption($member))
                    ->values(),
                'cards' => $cards->map(fn (PhysicalCard $card) => $this->transformCard($card))->values(),
            ],
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $this->validatePayload($request, $currentTenant, null);
        $userId = $this->backofficeUserId($request);

        $card = PhysicalCard::query()->create([
            'tenant_id' => $currentTenant->id(),
            'card_type' => $data['card_type'],
            'voucher_template_id' => $data['card_type'] === PhysicalCard::TYPE_VOUCHER ? ($data['voucher_template_id'] ?? null) : null,
            'badge_template_id' => in_array($data['card_type'], [PhysicalCard::TYPE_STAFF, PhysicalCard::TYPE_MEMBER], true) ? ($data['badge_template_id'] ?? null) : null,
            'holder_type' => $this->holderTypeForCardType($data['card_type']),
            'holder_id' => $data['holder_id'] ?? null,
            'label' => $this->nullableValue($data['label'] ?? null),
            'internal_reference' => $this->nullableValue($data['internal_reference'] ?? null),
            'rfid_uid' => trim($data['rfid_uid']),
            'status' => $data['status'] ?? PhysicalCard::STATUS_STOCK,
            'notes' => $this->nullableValue($data['notes'] ?? null),
            'printed_at' => ! empty($data['printed_at']) ? $data['printed_at'] : null,
            'issued_at' => ! empty($data['issued_at']) ? $data['issued_at'] : Carbon::today(),
            'returned_at' => ! empty($data['returned_at']) ? $data['returned_at'] : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if (blank($card->label)) {
            $card->forceFill(['label' => $this->defaultLabelForCard($card)])->save();
        }

        $card->load($this->detailRelations());

        return response()->json(['data' => $this->transformCard($card)], 201);
    }

    public function update(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $data = $this->validatePayload($request, $currentTenant, $card->id);
        $userId = $this->backofficeUserId($request);

        $card->fill([
            'card_type' => $data['card_type'],
            'voucher_template_id' => $data['card_type'] === PhysicalCard::TYPE_VOUCHER ? ($data['voucher_template_id'] ?? null) : null,
            'badge_template_id' => in_array($data['card_type'], [PhysicalCard::TYPE_STAFF, PhysicalCard::TYPE_MEMBER], true) ? ($data['badge_template_id'] ?? null) : null,
            'holder_type' => $this->holderTypeForCardType($data['card_type']),
            'holder_id' => $data['holder_id'] ?? null,
            'label' => $this->nullableValue($data['label'] ?? null),
            'internal_reference' => $this->nullableValue($data['internal_reference'] ?? null),
            'rfid_uid' => trim($data['rfid_uid']),
            'status' => $data['status'] ?? $card->status,
            'notes' => $this->nullableValue($data['notes'] ?? null),
            'printed_at' => ! empty($data['printed_at']) ? $data['printed_at'] : null,
            'issued_at' => ! empty($data['issued_at']) ? $data['issued_at'] : ($card->issued_at ?? Carbon::today()),
            'returned_at' => ! empty($data['returned_at']) ? $data['returned_at'] : null,
            'updated_by' => $userId,
        ]);

        if (blank($card->label)) {
            $card->label = $this->defaultLabelForCard($card);
        }

        $card->save();
        $card->load($this->detailRelations());

        return response()->json(['data' => $this->transformCard($card)]);
    }

    public function uploadRenderImage(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'data_url' => ['required', 'string'],
        ]);

        $binary = $this->decodePngDataUrl($data['data_url']);
        abort_if($binary === null, 422, 'Ongeldige PNG render ontvangen.');

        $path = sprintf('card-previews/tenant-%d/card-%d.png', $currentTenant->id(), $card->id);
        Storage::disk('public')->put($path, $binary);

        $card->forceFill([
            'render_image_path' => $path,
            'updated_by' => $this->backofficeUserId($request),
        ])->save();

        $card->load($this->detailRelations());

        return response()->json([
            'data' => $this->transformCard($card),
        ]);
    }

    public function markPrinted(Request $request, CurrentTenant $currentTenant, PhysicalCard $card): JsonResponse
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);
        abort_if(blank($card->render_image_path) || ! Storage::disk('public')->exists($card->render_image_path), 422, 'Voor deze kaart is nog geen PNG-preview beschikbaar. Sla de kaart opnieuw op of laad de preview opnieuw.');

        $card->forceFill([
            'printed_at' => Carbon::now(),
            'updated_by' => $this->backofficeUserId($request),
        ])->save();

        $card->load($this->detailRelations());

        return response()->json([
            'data' => [
                'card' => $this->transformCard($card),
                'pdf_url' => url('/api/backoffice/cards/' . $card->id . '/pdf'),
                'print_url' => url('/api/backoffice/cards/' . $card->id . '/pdf'),
            ],
        ]);
    }

    public function pdf(Request $request, CurrentTenant $currentTenant, PhysicalCard $card, SimpleImagePdfService $pdfService)
    {
        abort_unless((int) $card->tenant_id === (int) $currentTenant->id(), 404);
        abort_if(blank($card->render_image_path) || ! Storage::disk('public')->exists($card->render_image_path), 404, 'Voor deze kaart is nog geen render-afbeelding beschikbaar.');

        $pngBinary = Storage::disk('public')->get($card->render_image_path);
        $pdfBinary = $pdfService->fromPngString($pngBinary, 85.60, 53.98);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="card-' . $card->id . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    protected function validatePayload(Request $request, CurrentTenant $currentTenant, ?int $cardId): array
    {
        $data = $request->validate([
            'card_type' => ['required', Rule::in(array_keys(PhysicalCard::typeOptions()))],
            'voucher_template_id' => ['nullable', 'integer'],
            'badge_template_id' => ['nullable', 'integer'],
            'holder_id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:255'],
            'internal_reference' => ['nullable', 'string', 'max:255'],
            'rfid_uid' => ['required', 'string', 'max:255', 'unique:physical_cards,rfid_uid,' . ($cardId ?? 'NULL') . ',id,tenant_id,' . $currentTenant->id()],
            'status' => ['nullable', 'in:' . implode(',', array_keys(PhysicalCard::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'printed_at' => ['nullable', 'date'],
            'issued_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
        ]);

        if (($data['card_type'] ?? null) === PhysicalCard::TYPE_VOUCHER) {
            abort_if(empty($data['voucher_template_id']), 422, 'Selecteer een voucher type.');
            abort_unless(
                VoucherTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', (int) $data['voucher_template_id'])
                    ->exists(),
                422,
                'Ongeldig voucher type geselecteerd.'
            );
        }

        if (($data['card_type'] ?? null) === PhysicalCard::TYPE_STAFF) {
            abort_if(empty($data['badge_template_id']), 422, 'Selecteer een staff badge template.');
            abort_if(empty($data['holder_id']), 422, 'Selecteer een medewerker voor deze kaart.');
            abort_unless(
                BadgeTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('template_type', PhysicalCard::TYPE_STAFF)
                    ->where('id', (int) $data['badge_template_id'])
                    ->exists(),
                422,
                'Ongeldige staff badge template geselecteerd.'
            );
            abort_unless(
                User::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', (int) $data['holder_id'])
                    ->exists(),
                422,
                'Ongeldige medewerker geselecteerd.'
            );
        }

        if (($data['card_type'] ?? null) === PhysicalCard::TYPE_MEMBER) {
            abort_if(empty($data['badge_template_id']), 422, 'Selecteer een member badge template.');
            abort_if(empty($data['holder_id']), 422, 'Selecteer een lid voor deze kaart.');
            abort_unless(
                BadgeTemplate::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('template_type', PhysicalCard::TYPE_MEMBER)
                    ->where('id', (int) $data['badge_template_id'])
                    ->exists(),
                422,
                'Ongeldige member badge template geselecteerd.'
            );
            abort_unless(
                Member::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', (int) $data['holder_id'])
                    ->exists(),
                422,
                'Ongeldig lid geselecteerd.'
            );
        }

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
            'card_type' => PhysicalCard::TYPE_VOUCHER,
        ];
    }

    protected function transformBadgeTemplate(BadgeTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'template_type' => $template->template_type,
            'template_type_label' => PhysicalCard::typeOptions()[$template->template_type] ?? ucfirst((string) $template->template_type),
        ];
    }

    protected function transformCard(PhysicalCard $card): array
    {
        $renderData = null;

        try {
            $renderData = PhysicalCardRenderData::build($card);
        } catch (\Throwable $e) {
            $renderData = null;
        }

        $holderName = null;
        if ($card->card_type === PhysicalCard::TYPE_STAFF) {
            $holderName = $card->staffHolder?->name;
        } elseif ($card->card_type === PhysicalCard::TYPE_MEMBER) {
            $holderName = trim(implode(' ', array_filter([$card->memberHolder?->first_name, $card->memberHolder?->last_name])));
        }

        return [
            'id' => $card->id,
            'card_type' => $card->card_type ?: PhysicalCard::TYPE_VOUCHER,
            'card_type_label' => PhysicalCard::typeOptions()[$card->card_type ?: PhysicalCard::TYPE_VOUCHER] ?? ($card->card_type ?: PhysicalCard::TYPE_VOUCHER),
            'holder_type' => $card->holder_type,
            'holder_id' => $card->holder_id,
            'holder_name' => $holderName ?: null,
            'voucher_template_id' => $card->voucher_template_id,
            'voucher_template_name' => $card->voucherTemplate?->name,
            'badge_template_id' => $card->badge_template_id,
            'badge_template_name' => $card->card_type === PhysicalCard::TYPE_VOUCHER ? $card->voucherTemplate?->badgeTemplate?->name : $card->badgeTemplate?->name,
            'product_name' => $card->voucherTemplate?->product?->name,
            'product_price_incl_vat' => $card->voucherTemplate?->product?->price_incl_vat,
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
            'preview_image_url' => $card->render_image_path ? Storage::disk('public')->url($card->render_image_path) . '?v=' . urlencode((string) optional($card->updated_at)->timestamp) : null,
            'render_image_path' => $card->render_image_path,
            'pdf_url' => $card->render_image_path ? url('/api/backoffice/cards/' . $card->id . '/pdf') : null,
            'updated_at_label' => optional($card->updated_at)->format('d/m/Y H:i'),
            'render_template' => $renderData['template'] ?? null,
            'render_fields' => $renderData['fields'] ?? null,
        ];
    }

    protected function detailRelations(): array
    {
        return PhysicalCardRenderData::loadRelations();
    }

    protected function backofficeUserId(Request $request): ?int
    {
        return $request->attributes->get('backoffice_user')?->id;
    }

    protected function nullableValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    protected function decodePngDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[1], true);

        return $binary === false ? null : $binary;
    }

    protected function holderTypeForCardType(string $cardType): ?string
    {
        return match ($cardType) {
            PhysicalCard::TYPE_STAFF => PhysicalCard::TYPE_STAFF,
            PhysicalCard::TYPE_MEMBER => PhysicalCard::TYPE_MEMBER,
            default => null,
        };
    }

    protected function defaultLabelForCard(PhysicalCard $card): string
    {
        return match ($card->card_type) {
            PhysicalCard::TYPE_STAFF => 'STAFF #' . $card->id,
            PhysicalCard::TYPE_MEMBER => 'MEMBER #' . $card->id,
            default => 'CARD #' . $card->id,
        };
    }

    protected function transformStaffOption(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'rfid_uid' => $user->rfid_uid,
        ];
    }

    protected function transformMemberOption(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => trim(implode(' ', array_filter([$member->first_name, $member->last_name]))),
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'login' => $member->login,
            'email' => $member->email,
            'rfid_uid' => $member->rfid_uid,
        ];
    }
}

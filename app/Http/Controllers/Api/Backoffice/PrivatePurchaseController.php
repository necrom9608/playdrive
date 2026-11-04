<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Purchases\PrivatePurchaseService;
use App\Http\Controllers\Controller;
use App\Models\AccountingExportSetting;
use App\Models\PrivatePurchase;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backoffice: aankopen van particulieren (aankoopborderel met digitale handtekening).
 */
class PrivatePurchaseController extends Controller
{
    private const MAX_PHOTOS = 8;

    public function __construct(
        private readonly PrivatePurchaseService $service,
    ) {
    }

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $tenantId = $currentTenant->id();

        $query = PrivatePurchase::query()->where('tenant_id', $tenantId);

        $status = $request->query('status');
        if (in_array($status, [
            PrivatePurchase::STATUS_DRAFT, PrivatePurchase::STATUS_SENT, PrivatePurchase::STATUS_SIGNED,
            PrivatePurchase::STATUS_PAID, PrivatePurchase::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('number', 'like', $like)
                    ->orWhere('seller_first_name', 'like', $like)
                    ->orWhere('seller_last_name', 'like', $like)
                    ->orWhere('invite_email', 'like', $like)
                    ->orWhere('items', 'like', $like);
            });
        }

        $purchases = $query->latest('id')->limit(300)->get();

        $quarterStart = CarbonImmutable::now(PrivatePurchase::TIMEZONE)->firstOfQuarter()->toDateString();

        $base = PrivatePurchase::query()->where('tenant_id', $tenantId);

        return response()->json([
            'data' => $purchases->map(fn (PrivatePurchase $p) => $this->summary($p))->values(),
            'stats' => [
                'awaiting_signature' => (clone $base)->where('status', PrivatePurchase::STATUS_SENT)->count(),
                'unpaid_amount' => (float) (clone $base)->where('status', PrivatePurchase::STATUS_SIGNED)->sum('total_amount'),
                'unpaid_count' => (clone $base)->where('status', PrivatePurchase::STATUS_SIGNED)->count(),
                'quarter_total' => (float) (clone $base)
                    ->whereIn('status', [PrivatePurchase::STATUS_SIGNED, PrivatePurchase::STATUS_PAID])
                    ->whereDate('purchase_date', '>=', $quarterStart)
                    ->sum('total_amount'),
            ],
        ]);
    }

    public function defaults(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');
        $tenant = $currentTenant->tenant;

        return response()->json(['data' => [
            'tenant_email' => $tenant->email,
            'accounting_recipients' => AccountingExportSetting::forTenant($tenant->id)->recipientList(),
            'sign_place' => $tenant->city,
            'cash_limit' => PrivatePurchase::CASH_LIMIT,
            'link_days' => PrivatePurchase::LINK_DAYS,
        ]]);
    }

    public function show(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        return response()->json(['data' => $this->detail($model)]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        $data = $this->validatePayload($request);
        $user = $request->attributes->get('backoffice_user');

        $purchase = $this->service->create($currentTenant->tenant, $data, $user?->id);

        return response()->json([
            'message' => 'Aankoop ' . $purchase->number . ' opgeslagen.',
            'data' => $this->detail($purchase->refresh()),
        ], 201);
    }

    public function update(Request $request, int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);
        $data = $this->validatePayload($request);

        try {
            $this->service->update($model, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Opgeslagen.',
            'data' => $this->detail($model->refresh()),
        ]);
    }

    public function share(Request $request, int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);
        $sendMail = $request->boolean('send_mail');

        if (count($model->items ?? []) === 0) {
            return response()->json(['message' => 'Voeg minstens één artikel toe.'], 422);
        }

        try {
            $this->service->share($model, $currentTenant->tenant, $sendMail);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => $this->detail($model->refresh())], 422);
        }

        return response()->json([
            'message' => $sendMail ? 'Link gemaild naar ' . $model->invite_email . '.' : 'Link actief.',
            'data' => $this->detail($model->refresh()),
        ]);
    }

    public function markPaid(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if ($model->status !== PrivatePurchase::STATUS_SIGNED) {
            return response()->json(['message' => 'Enkel een ondertekend borderel kan als betaald gemarkeerd worden.'], 422);
        }

        $model->forceFill(['status' => PrivatePurchase::STATUS_PAID, 'paid_at' => now()])->save();

        return response()->json(['message' => 'Gemarkeerd als betaald.', 'data' => $this->detail($model)]);
    }

    public function markUnpaid(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if ($model->status !== PrivatePurchase::STATUS_PAID) {
            return response()->json(['message' => 'Dit borderel staat niet op betaald.'], 422);
        }

        $model->forceFill(['status' => PrivatePurchase::STATUS_SIGNED, 'paid_at' => null])->save();

        return response()->json(['message' => 'Terug op ondertekend gezet.', 'data' => $this->detail($model)]);
    }

    public function cancel(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if (in_array($model->status, [PrivatePurchase::STATUS_PAID, PrivatePurchase::STATUS_CANCELLED], true)) {
            return response()->json(['message' => 'Dit borderel kan niet meer geannuleerd worden.'], 422);
        }

        $model->forceFill([
            'status' => PrivatePurchase::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'token_expires_at' => now(),
        ])->save();

        return response()->json(['message' => 'Geannuleerd.', 'data' => $this->detail($model)]);
    }

    public function destroy(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if ($model->signed_at) {
            return response()->json(['message' => 'Een ondertekend borderel kan je niet verwijderen. Annuleer het indien nodig.'], 422);
        }

        $this->service->deleteFiles($model);
        $model->delete();

        return response()->json(['message' => 'Verwijderd.']);
    }

    public function resendPdf(int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if (! $model->isSigned()) {
            return response()->json(['message' => 'Het borderel is nog niet ondertekend.'], 422);
        }

        $result = $this->service->mailSigned($model->loadMissing('creator'), $currentTenant->tenant);

        if ($result['sent'] === []) {
            return response()->json([
                'message' => $result['errors'] ? 'Versturen mislukt: ' . implode(' | ', $result['errors']) : 'Geen ontvangers ingesteld.',
                'data' => $this->detail($model->refresh()),
            ], 422);
        }

        return response()->json([
            'message' => 'PDF verstuurd naar ' . implode(', ', $result['sent']) . '.',
            'data' => $this->detail($model->refresh()),
        ]);
    }

    public function pdf(int $purchase, CurrentTenant $currentTenant): Response
    {
        $model = $this->find($purchase, $currentTenant);

        try {
            $binary = $this->service->pdfBinary($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->service->pdfFilename($model) . '"',
        ]);
    }

    // ------------------------------------------------------------------
    // Foto's
    // ------------------------------------------------------------------

    public function uploadPhoto(Request $request, int $purchase, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if ($model->isLocked()) {
            return response()->json(['message' => 'Dit borderel is vergrendeld.'], 422);
        }

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ], [
            'photo.max' => 'Foto is te groot (max. 12 MB).',
            'photo.image' => 'Dit is geen afbeelding.',
        ]);

        $photos = $model->photos ?? [];
        if (count($photos) >= self::MAX_PHOTOS) {
            return response()->json(['message' => 'Maximaal ' . self::MAX_PHOTOS . " foto's per borderel."], 422);
        }

        $file = $request->file('photo');
        $name = Str::random(20) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = $file->storeAs($this->service->photoDirectory($model), $name, 'local');

        $photos[] = ['path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 120)];
        $model->forceFill(['photos' => $photos])->save();

        return response()->json(['message' => 'Foto toegevoegd.', 'data' => $this->detail($model)]);
    }

    public function deletePhoto(int $purchase, int $index, CurrentTenant $currentTenant): JsonResponse
    {
        $model = $this->find($purchase, $currentTenant);

        if ($model->isLocked()) {
            return response()->json(['message' => 'Dit borderel is vergrendeld.'], 422);
        }

        $photos = $model->photos ?? [];
        if (! isset($photos[$index])) {
            abort(404);
        }

        Storage::disk('local')->delete($photos[$index]['path'] ?? '');
        array_splice($photos, $index, 1);
        $model->forceFill(['photos' => array_values($photos) ?: null])->save();

        return response()->json(['message' => 'Foto verwijderd.', 'data' => $this->detail($model)]);
    }

    public function photo(int $purchase, int $index, CurrentTenant $currentTenant): Response
    {
        $model = $this->find($purchase, $currentTenant);
        $path = ($model->photos ?? [])[$index]['path'] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=3600']);
    }

    // ------------------------------------------------------------------
    // Intern
    // ------------------------------------------------------------------

    private function find(int $id, CurrentTenant $currentTenant): PrivatePurchase
    {
        abort_unless($currentTenant->exists(), 422, 'Geen tenant gevonden.');

        return PrivatePurchase::query()
            ->where('tenant_id', $currentTenant->id())
            ->with('creator:id,name')
            ->findOrFail($id);
    }

    private function validatePayload(Request $request): array
    {
        $request->merge(['extra_recipients' => $this->parseEmails($request->input('extra_recipients'))]);

        $data = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', 'in:transfer,cash'],
            'purchase_date' => ['required', 'date'],
            'sign_place' => ['nullable', 'string', 'max:100'],
            'invite_email' => ['nullable', 'email', 'max:255'],
            'mail_seller' => ['boolean'],
            'mail_tenant' => ['boolean'],
            'mail_accounting' => ['boolean'],
            'extra_recipients' => ['array', 'max:5'],
            'extra_recipients.*' => ['email', 'max:255'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'items.required' => 'Voeg minstens één artikel toe.',
            'items.min' => 'Voeg minstens één artikel toe.',
            'items.*.description.required' => 'Elke regel heeft een omschrijving nodig.',
            'items.*.quantity.min' => 'Het aantal moet groter zijn dan 0.',
            'invite_email.email' => 'Het e-mailadres van de verkoper is ongeldig.',
            'extra_recipients.*.email' => '":input" is geen geldig e-mailadres.',
        ])->after(function ($validator) use ($request) {
            if ($request->input('payment_method') !== PrivatePurchase::PAYMENT_CASH) {
                return;
            }
            $total = $this->service->total($this->service->normalizeItems((array) $request->input('items', [])));
            if ($total > PrivatePurchase::CASH_LIMIT) {
                $validator->errors()->add('payment_method', 'Contant betalen kan enkel tot ' . PrivatePurchase::money(PrivatePurchase::CASH_LIMIT) . '. Kies voor overschrijving.');
            }
        })->validate();

        $data['invite_email'] = isset($data['invite_email']) ? strtolower(trim($data['invite_email'])) : null;

        return $data;
    }

    private function parseEmails(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,;]+/', $value) ?: [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $value))));
    }

    private function summary(PrivatePurchase $p): array
    {
        return [
            'id' => $p->id,
            'number' => $p->number,
            'status' => $p->status,
            'purchase_date' => $p->purchase_date?->toDateString(),
            'seller_name' => $p->sellerName(),
            'invite_email' => $p->invite_email,
            'items_summary' => $p->itemsSummary(),
            'total_amount' => (float) $p->total_amount,
            'payment_method' => $p->payment_method,
            'link_active' => $p->linkIsActive(),
            'link_expired' => $p->status === PrivatePurchase::STATUS_SENT && ! $p->linkIsActive(),
            'opened_at' => $p->opened_at?->toIso8601String(),
            'signed_at' => $p->signed_at?->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'mail_error' => $p->mail_error,
        ];
    }

    private function detail(PrivatePurchase $p): array
    {
        return array_merge($this->summary($p), [
            'items' => $p->items ?? [],
            'sign_place' => $p->sign_place,
            'internal_note' => $p->internal_note,
            'mail_seller' => (bool) $p->mail_seller,
            'mail_tenant' => (bool) $p->mail_tenant,
            'mail_accounting' => (bool) $p->mail_accounting,
            'extra_recipients' => $p->extra_recipients ?? [],
            'photos' => collect($p->photos ?? [])->values()->map(fn ($photo, $i) => [
                'index' => $i,
                'name' => $photo['name'] ?? ('Foto ' . ($i + 1)),
                'url' => '/api/backoffice/purchases/' . $p->id . '/photos/' . $i,
            ])->all(),
            'public_url' => $p->linkIsActive() ? $p->publicUrl() : null,
            'token_expires_at' => $p->token_expires_at?->toIso8601String(),
            'sent_via' => $p->sent_via,
            'invited_at' => $p->invited_at?->toIso8601String(),
            'locked' => $p->isLocked(),
            'seller' => $p->signed_at ? [
                'first_name' => $p->seller_first_name,
                'last_name' => $p->seller_last_name,
                'street' => $p->seller_street,
                'postal_code' => $p->seller_postal_code,
                'city' => $p->seller_city,
                'country' => $p->seller_country,
                'email' => $p->seller_email,
                'phone' => $p->seller_phone,
                'iban' => PrivatePurchase::formatIban($p->seller_iban),
                'iban_holder' => $p->seller_iban_holder,
            ] : null,
            'signed_ip' => $p->signed_ip,
            'document_hash' => $p->document_hash,
            'pdf_url' => $p->isSigned() ? '/api/backoffice/purchases/' . $p->id . '/pdf' : null,
            'mailed_at' => $p->mailed_at?->toIso8601String(),
            'mailed_to' => $p->mailed_to ?? [],
            'cancelled_at' => $p->cancelled_at?->toIso8601String(),
            'created_by' => $p->creator?->name,
            'created_at' => $p->created_at?->toIso8601String(),
        ]);
    }
}

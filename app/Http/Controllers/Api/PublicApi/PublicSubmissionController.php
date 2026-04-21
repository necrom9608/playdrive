<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\CateringOption;
use App\Models\EventType;
use App\Models\GiftVoucher;
use App\Models\Registration;
use App\Models\StayOption;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\ReservationMailService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicSubmissionController extends Controller
{
    public function storeReservation(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $currentTenant);
        abort_unless($tenant, 404, 'Geen geldige tenant gevonden voor deze aanvraag.');

        $data = $this->validateReservationPayload($request, $tenant->id);

        $registration = Registration::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'phone' => $this->nullableValue($data['phone'] ?? null),
            'email' => $this->nullableValue($data['email'] ?? null),
            'postal_code' => $this->nullableValue($data['postal_code'] ?? null),
            'municipality' => $this->nullableValue($data['municipality'] ?? null),
            'event_type_id' => $data['event_type_id'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'event_time' => $data['event_time'] ?? null,
            'stay_option_id' => $data['stay_option_id'] ?? null,
            'catering_option_id' => $data['catering_option_id'] ?? null,
            'participants_children' => (int) ($data['participants_children'] ?? 0),
            'participants_adults' => (int) ($data['participants_adults'] ?? 0),
            'participants_supervisors' => (int) ($data['participants_supervisors'] ?? 0),
            'comment' => $this->nullableValue($data['comment'] ?? null),
            'stats' => $data['stats'] ?? [],
            'status' => ($data['outside_opening_hours'] ?? false)
                ? Registration::STATUS_PENDING
                : Registration::STATUS_NEW,
            'invoice_requested' => (bool) ($data['invoice_requested'] ?? false),
            'invoice_company_name' => $this->nullableValue($data['invoice_company_name'] ?? null),
            'invoice_vat_number' => $this->nullableValue($data['invoice_vat_number'] ?? null),
            'invoice_email' => $this->nullableValue($data['invoice_email'] ?? null),
            'invoice_address' => $this->nullableValue($data['invoice_address'] ?? null),
            'invoice_postal_code' => $this->nullableValue($data['invoice_postal_code'] ?? null),
            'invoice_city' => $this->nullableValue($data['invoice_city'] ?? null),
            'outside_opening_hours' => (bool) ($data['outside_opening_hours'] ?? false),
        ]);

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        // Bevestigings- en notificatiemail versturen
        // Stuur de juiste mail op basis van de status
        if ($registration->status === Registration::STATUS_PENDING) {
            ReservationMailService::sendPendingAcknowledgement($registration, $tenant);
        } else {
            ReservationMailService::sendAfterSubmission($registration, $tenant);
        }

        return response()->json([
            'message' => 'Reservatie succesvol aangemaakt.',
            'data' => $this->transformRegistration($registration),
        ], 201);
    }

    public function storeGiftVoucher(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $currentTenant);
        abort_unless($tenant, 404, 'Geen geldige tenant gevonden voor deze aanvraag.');

        $data = $this->validateGiftVoucherPayload($request, $tenant->id);

        $voucher = GiftVoucher::query()->create([
            'tenant_id' => $tenant->id,
            'code' => $this->nullableValue($data['code'] ?? null) ?: $this->generateVoucherCode(),
            'qr_token' => $this->nullableValue($data['qr_token'] ?? null) ?: Str::uuid()->toString(),
            'nfc_uid' => $this->nullableValue($data['nfc_uid'] ?? null),
            'name' => $this->nullableValue($data['name'] ?? null),
            'customer_name' => $this->nullableValue($data['customer_name'] ?? null),
            'customer_email' => $this->nullableValue($data['customer_email'] ?? null),
            'source_channel' => 'website',
            'status' => $data['status'] ?? GiftVoucher::STATUS_ACTIVE,
            'amount_initial' => $data['amount_initial'],
            'amount_remaining' => $data['amount_remaining'] ?? $data['amount_initial'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Cadeaubon succesvol aangemaakt.',
            'data' => $this->transformVoucher($voucher),
        ], 201);
    }

    protected function validateReservationPayload(Request $request, int $tenantId): array
    {
        return Validator::make($request->all(), [
            'tenant' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'event_type_id' => [
                'nullable',
                'integer',
                Rule::exists('event_types', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'stay_option_id' => [
                'nullable',
                'integer',
                Rule::exists('stay_options', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'catering_option_id' => [
                'nullable',
                'integer',
                Rule::exists('catering_options', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'participants_children' => ['nullable', 'integer', 'min:0'],
            'participants_adults' => ['nullable', 'integer', 'min:0'],
            'participants_supervisors' => ['nullable', 'integer', 'min:0'],
            'comment' => ['nullable', 'string'],
            'stats' => ['nullable', 'array'],
            'stats.already_visited' => ['nullable', 'boolean'],
            'stats.recommended_by_friend' => ['nullable', 'boolean'],
            'stats.internet' => ['nullable', 'boolean'],
            'stats.social_media' => ['nullable', 'boolean'],
            'stats.facade' => ['nullable', 'boolean'],
            'stats.ai' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in([
                Registration::STATUS_NEW,
                Registration::STATUS_CONFIRMED,
            ])],
            'invoice_requested' => ['nullable', 'boolean'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_vat_number' => ['nullable', 'string', 'max:255'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'invoice_address' => ['nullable', 'string', 'max:255'],
            'invoice_postal_code' => ['nullable', 'string', 'max:10'],
            'invoice_city' => ['nullable', 'string', 'max:255'],
            'outside_opening_hours' => ['nullable', 'boolean'],
        ])->after(function ($validator) use ($request) {
            $children = (int) $request->input('participants_children', 0);
            $adults = (int) $request->input('participants_adults', 0);
            $supervisors = (int) $request->input('participants_supervisors', 0);

            if (($children + $adults + $supervisors) <= 0) {
                $validator->errors()->add('participants_total', 'Er moet minstens 1 deelnemer opgegeven worden.');
            }
        })->validate();
    }

    protected function validateGiftVoucherPayload(Request $request, int $tenantId): array
    {
        return Validator::make($request->all(), [
            'tenant' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('gift_vouchers', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'qr_token' => ['nullable', 'string', 'max:255', Rule::unique('gift_vouchers', 'qr_token')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'nfc_uid' => ['nullable', 'string', 'max:255', Rule::unique('gift_vouchers', 'nfc_uid')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in([
                GiftVoucher::STATUS_ACTIVE,
                GiftVoucher::STATUS_VALIDATED,
                GiftVoucher::STATUS_REDEEMED,
                GiftVoucher::STATUS_CANCELLED,
                GiftVoucher::STATUS_EXPIRED,
            ])],
            'amount_initial' => ['required', 'numeric', 'min:0.01'],
            'amount_remaining' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
        ])->validate();
    }

    protected function resolveTenant(Request $request, CurrentTenant $currentTenant): ?Tenant
    {
        if ($currentTenant->exists()) {
            return $currentTenant->tenant;
        }

        $tenantInput = trim((string) (
            $request->input('tenant')
            ?? $request->header('X-Tenant')
            ?? $request->query('tenant')
            ?? ''
        ));

        if ($tenantInput === '') {
            return null;
        }

        $normalized = Str::lower($tenantInput);

        $tenantByDomain = TenantDomain::query()
            ->with('tenant')
            ->whereRaw('lower(domain) = ?', [$normalized])
            ->first();

        if ($tenantByDomain?->tenant?->is_active) {
            return $tenantByDomain->tenant;
        }

        return Tenant::query()
            ->where('slug', $normalized)
            ->where('is_active', true)
            ->first();
    }

    protected function transformRegistration(Registration $registration): array
    {
        return [
            'id' => $registration->id,
            'name' => $registration->name,
            'phone' => $registration->phone,
            'email' => $registration->email,
            'postal_code' => $registration->postal_code,
            'municipality' => $registration->municipality,
            'event_type_id' => $registration->event_type_id,
            'event_type' => $registration->eventType?->name,
            'stay_option_id' => $registration->stay_option_id,
            'stay_option' => $registration->stayOption?->name,
            'catering_option_id' => $registration->catering_option_id,
            'catering_option' => $registration->cateringOption?->name,
            'event_date' => optional($registration->event_date)->format('Y-m-d'),
            'event_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
            'participants_children' => (int) $registration->participants_children,
            'participants_adults' => (int) $registration->participants_adults,
            'participants_supervisors' => (int) $registration->participants_supervisors,
            'total_count' => $registration->total_participants,
            'comment' => $registration->comment,
            'stats' => $registration->stats ?? [],
            'status' => $registration->status,
            'invoice_requested' => (bool) $registration->invoice_requested,
            'outside_opening_hours' => (bool) $registration->outside_opening_hours,
            'created_at' => optional($registration->created_at)->toIso8601String(),
        ];
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
            'status' => $voucher->status,
            'amount_initial' => (float) $voucher->amount_initial,
            'amount_remaining' => (float) $voucher->amount_remaining,
            'expires_at' => optional($voucher->expires_at)->format('Y-m-d'),
            'created_at' => optional($voucher->created_at)->toIso8601String(),
        ];
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

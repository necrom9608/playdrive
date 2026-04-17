<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Orders\OrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreRegistrationRequest;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Registration;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {
    }

    // ------------------------------------------------------------------
    // Eager-load relaties — centraal zodat alle methodes consistent zijn
    // ------------------------------------------------------------------

    private function withRelations(): array
    {
        return [
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
            'account:id,first_name,last_name,email',
            'createdBy:id,name',
            'updatedBy:id,name',
            'checkedInBy:id,name',
            'checkedOutBy:id,name',
            'cancelledBy:id,name',
            'noShowBy:id,name',
        ];
    }

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $query = Registration::query()->with($this->withRelations());

        if ($currentTenant->exists()) {
            $query->where('tenant_id', $currentTenant->id());
        }

        $view = strtolower((string) $request->get('view', 'today'));
        $date = $request->get('date');

        if ($view === 'open') {
            $query->whereIn('status', [
                Registration::STATUS_NEW,
                Registration::STATUS_CONFIRMED,
            ]);
        } else {
            $query->whereDate('event_date', $date ?: now()->toDateString());
        }

        $registrations = $query
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->orderBy('id')
            ->get()
            ->map(fn (Registration $r) => $this->transformRegistration($r));

        return response()->json(['data' => $registrations]);
    }

    public function store(StoreRegistrationRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data        = $request->validated();
        $actorUserId = $this->frontdeskUserId($request);

        if ($currentTenant->exists()) {
            $data['tenant_id'] = $currentTenant->id();
        }

        $data['created_by'] = $actorUserId;
        $data['updated_by'] = $actorUserId;

        // Als enkel member_id meegegeven: account_id afleiden
        if (empty($data['account_id']) && ! empty($data['member_id'])) {
            $data['account_id'] = $this->accountIdForMember((int) $data['member_id']);
        }

        if (($data['status'] ?? null) === Registration::STATUS_CHECKED_IN && empty($data['checked_in_at'])) {
            $data['checked_in_at'] = now();
            $data['checked_in_by'] = $actorUserId;
        }
        if (($data['status'] ?? null) === Registration::STATUS_CANCELLED && empty($data['cancelled_at'])) {
            $data['cancelled_at'] = now();
            $data['cancelled_by'] = $actorUserId;
        }
        if (($data['status'] ?? null) === Registration::STATUS_NO_SHOW && empty($data['no_show_at'])) {
            $data['no_show_at'] = now();
            $data['no_show_by'] = $actorUserId;
        }

        $registration = Registration::create($data);
        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Registratie opgeslagen.',
            'data'    => $this->transformRegistration($registration),
        ], 201);
    }

    public function update(
        StoreRegistrationRequest $request,
        Registration $registration,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $data        = $request->validated();
        $actorUserId = $this->frontdeskUserId($request);

        $data['updated_by'] = $actorUserId;

        // Als enkel member_id meegegeven: account_id afleiden
        if (empty($data['account_id']) && ! empty($data['member_id'])) {
            $data['account_id'] = $this->accountIdForMember((int) $data['member_id']);
        }

        if (($data['status'] ?? null) === Registration::STATUS_CHECKED_IN && ! $registration->checked_in_at) {
            $data['checked_in_at'] = now();
            $data['checked_in_by'] = $registration->checked_in_by ?: $actorUserId;
        }
        if (($data['status'] ?? null) === Registration::STATUS_CHECKED_OUT && ! $registration->checked_out_at) {
            $data['checked_out_at'] = now();
            $data['checked_out_by'] = $registration->checked_out_by ?: $actorUserId;
        }
        if (($data['status'] ?? null) === Registration::STATUS_CANCELLED && ! $registration->cancelled_at) {
            $data['cancelled_at'] = now();
            $data['cancelled_by'] = $registration->cancelled_by ?: $actorUserId;
        }
        if (($data['status'] ?? null) === Registration::STATUS_NO_SHOW && ! $registration->no_show_at) {
            $data['no_show_at'] = now();
            $data['no_show_by'] = $registration->no_show_by ?: $actorUserId;
        }

        $registration->update($data);
        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Registratie bijgewerkt.',
            'data'    => $this->transformRegistration($registration),
        ]);
    }

    public function checkIn(Request $request, Registration $registration, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $actorUserId = $this->frontdeskUserId($request);

        $registration->status       = Registration::STATUS_CHECKED_IN;
        $registration->checked_in_at = now();
        $registration->checked_in_by = $actorUserId;
        $registration->updated_by   = $actorUserId;
        $registration->save();

        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Registratie ingecheckt.',
            'data'    => $this->transformRegistration($registration),
        ]);
    }

    public function checkOut(
        Request $request,
        Registration $registration,
        CurrentTenant $currentTenant
    ): JsonResponse {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $actorUserId = $this->frontdeskUserId($request);

        DB::transaction(function () use ($registration, $actorUserId) {
            $registration->status        = Registration::STATUS_CHECKED_OUT;
            $registration->checked_out_at = now();
            $registration->checked_out_by = $actorUserId;
            $registration->updated_by    = $actorUserId;

            if ($registration->checked_in_at) {
                $registration->played_minutes = max(
                    0,
                    $registration->checked_in_at->diffInMinutes($registration->checked_out_at)
                );
            }

            $registration->save();
        });

        $registration->load($this->withRelations());

        $order = null;
        if (! $registration->is_member) {
            $order = $this->orderService->syncPricingForRegistration($registration, $currentTenant);
        }

        return response()->json([
            'message' => 'Registratie uitgecheckt.',
            'data'    => $this->transformRegistration($registration),
            'order'   => $order ? $this->transformOrderForPos($order) : null,
        ]);
    }

    public function cancel(Request $request, Registration $registration, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $actorUserId = $this->frontdeskUserId($request);

        $registration->status      = Registration::STATUS_CANCELLED;
        $registration->cancelled_at = now();
        $registration->cancelled_by = $actorUserId;
        $registration->updated_by  = $actorUserId;
        $registration->save();

        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Registratie geannuleerd.',
            'data'    => $this->transformRegistration($registration),
        ]);
    }

    public function noShow(Request $request, Registration $registration, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $actorUserId = $this->frontdeskUserId($request);

        $registration->status    = Registration::STATUS_NO_SHOW;
        $registration->no_show_at = now();
        $registration->no_show_by = $actorUserId;
        $registration->updated_by = $actorUserId;
        $registration->save();

        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Registratie op no-show gezet.',
            'data'    => $this->transformRegistration($registration),
        ]);
    }

    public function destroy(Registration $registration, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $registration->delete();

        return response()->json(['message' => 'Registratie verwijderd.']);
    }

    // ------------------------------------------------------------------
    // Transform helpers
    // ------------------------------------------------------------------

    protected function transformRegistration(Registration $registration): array
    {
        // account via nieuwe relatie; member als backward-compat fallback
        $account = $registration->account;

        return [
            'id'                    => $registration->id,
            'name'                  => $registration->name,
            'phone'                 => $registration->phone,
            'email'                 => $registration->email,
            'postal_code'           => $registration->postal_code,
            'municipality'          => $registration->municipality,
            'event_type_id'         => $registration->event_type_id,
            'event_date'            => optional($registration->event_date)->format('Y-m-d'),
            'event_time'            => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
            'stay_option_id'        => $registration->stay_option_id,
            'catering_option_id'    => $registration->catering_option_id,
            'participants_children' => $registration->participants_children,
            'participants_adults'   => $registration->participants_adults,
            'participants_supervisors' => $registration->participants_supervisors,
            'total_count'           => $registration->total_participants,
            'comment'               => $registration->comment,
            'stats'                 => $registration->stats ?? [],
            'status'                => $registration->status,
            'invoice_requested'     => $registration->invoice_requested,
            'invoice_company_name'  => $registration->invoice_company_name,
            'invoice_vat_number'    => $registration->invoice_vat_number,
            'invoice_email'         => $registration->invoice_email,
            'invoice_address'       => $registration->invoice_address,
            'invoice_postal_code'   => $registration->invoice_postal_code,
            'invoice_city'          => $registration->invoice_city,
            'checked_in_at'         => optional($registration->checked_in_at)?->toIso8601String(),
            'checked_in_by'         => $this->transformActor($registration->checkedInBy),
            'checked_out_at'        => optional($registration->checked_out_at)?->toIso8601String(),
            'checked_out_by'        => $this->transformActor($registration->checkedOutBy),
            'cancelled_at'          => optional($registration->cancelled_at)?->toIso8601String(),
            'cancelled_by'          => $this->transformActor($registration->cancelledBy),
            'no_show_at'            => optional($registration->no_show_at)?->toIso8601String(),
            'no_show_by'            => $this->transformActor($registration->noShowBy),
            'created_at'            => optional($registration->created_at)?->toIso8601String(),
            'updated_at'            => optional($registration->updated_at)?->toIso8601String(),
            'created_by'            => $this->transformActor($registration->createdBy),
            'updated_by'            => $this->transformActor($registration->updatedBy),
            'played_minutes'        => $registration->played_minutes,
            'outside_opening_hours' => $registration->outside_opening_hours,
            'is_member'             => (bool) $registration->is_member,
            'member_id'             => $registration->member_id,      // backward compat voor frontend
            'account_id'            => $registration->account_id,
            'member'                => $account ? $this->transformAccount($account) : null,
            'duration_label'        => $registration->stayOption?->name,
            'event_type'            => $registration->eventType?->name,
            'catering_option'       => $registration->cateringOption?->name,
            'event_type_code'       => $registration->eventType?->code,
            'catering_option_code'  => $registration->cateringOption?->code,
            'stay_duration_minutes' => $registration->stayOption?->duration_minutes,
            'event_type_emoji'      => $registration->eventType?->emoji,
            'catering_option_emoji' => $registration->cateringOption?->emoji,
        ];
    }

    protected function transformAccount(Account $account): array
    {
        return [
            'id'        => $account->id,
            'full_name' => trim($account->first_name . ' ' . $account->last_name),
            'email'     => $account->email,
        ];
    }

    protected function transformOrderForPos(Order $order): array
    {
        $order->loadMissing([
            'items.product',
            'creator:id,name',
            'updater:id,name',
            'payer:id,name',
            'canceller:id,name',
            'refunder:id,name',
            'items.creator:id,name',
            'items.updater:id,name',
        ]);

        return [
            'id'             => $order->id,
            'status'         => $order->status,
            'context'        => $order->source === Order::SOURCE_RESERVATION ? 'registration' : 'walk_in',
            'registration_id' => $order->registration_id,
            'reservation_id' => $order->registration_id,
            'subtotal_excl_vat' => (float) $order->subtotal_excl_vat,
            'total_vat'      => (float) $order->total_vat,
            'total_incl_vat' => (float) $order->total_incl_vat,
            'created_by'     => $this->transformActor($order->creator),
            'updated_by'     => $this->transformActor($order->updater),
            'paid_by'        => $this->transformActor($order->payer),
            'cancelled_by'   => $this->transformActor($order->canceller),
            'refunded_by'    => $this->transformActor($order->refunder),
            'items'          => $order->items
                ->sortBy('sort_order')
                ->map(fn (OrderItem $item) => [
                    'id'                  => $item->id,
                    'line_id'             => 'order-item-' . $item->id,
                    'product_id'          => $item->product_id,
                    'name'                => $item->name,
                    'unit_price_incl_vat' => (float) $item->unit_price_incl_vat,
                    'price_incl_vat'      => (float) $item->unit_price_incl_vat,
                    'unit_price'          => (float) $item->unit_price_incl_vat,
                    'quantity'            => (int) $item->quantity,
                    'line_total_incl_vat' => (float) $item->line_total_incl_vat,
                    'line_total'          => (float) $item->line_total_incl_vat,
                    'source'              => $item->source,
                    'source_reference'    => $item->source_reference,
                    'created_by'          => $this->transformActor($item->creator),
                    'updated_by'          => $this->transformActor($item->updater),
                ])
                ->values()
                ->all(),
        ];
    }

    protected function frontdeskUserId(Request $request): ?int
    {
        return $request->attributes->get('frontdesk_user')?->id;
    }

    protected function transformActor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'   => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * Leidt account_id af uit een member_id via de tenant_memberships brug.
     */
    private function accountIdForMember(int $memberId): ?int
    {
        return \App\Models\TenantMembership::query()
            ->where('legacy_member_id', $memberId)
            ->value('account_id');
    }
}

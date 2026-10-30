<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Orders\OrderService;
use App\Domain\Pricing\PricingContext;
use App\Domain\Pricing\PricingEvaluator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreRegistrationRequest;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Registration;
use App\Models\RegistrationEarlyStop;
use App\Models\RegistrationSubscriber;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'subscribers.tenantMembership',
            'earlyStops.subscriber.tenantMembership',
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
            // Startuur uit het formulier gebruiken als dat vandaag en niet in de toekomst ligt
            $data['checked_in_at'] = $this->startFromForm($data['event_date'] ?? null, $data['event_time'] ?? null) ?? now();
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

        $subscriberIds = $request->has('subscriber_membership_ids')
            ? array_map('intval', $data['subscriber_membership_ids'] ?? [])
            : null;
        unset($data['subscriber_membership_ids']);

        $registration = DB::transaction(function () use ($data, $subscriberIds, $currentTenant) {
            $registration = Registration::create($data);

            if ($subscriberIds !== null) {
                $this->syncSubscribers($registration, $subscriberIds, $currentTenant);
            }

            return $registration;
        });

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

        // Startuur aangepast bij een ingecheckte registratie → effectieve inchecktijd mee aanpassen
        $startChanged = false;
        if ($registration->checked_in_at && ! empty($data['event_time'])
            && $data['event_time'] !== $registration->checked_in_at->format('H:i')) {
            $newStart = \Illuminate\Support\Carbon::parse(
                $registration->checked_in_at->toDateString() . ' ' . $data['event_time']
            );

            if ($newStart->greaterThan(now()->addMinute())) {
                return response()->json([
                    'message' => 'Het startuur ligt in de toekomst.',
                    'errors'  => ['event_time' => ['Het startuur ligt in de toekomst.']],
                ], 422);
            }

            if ($registration->checked_out_at && $newStart->greaterThanOrEqualTo($registration->checked_out_at)) {
                return response()->json([
                    'message' => 'Het startuur ligt na het uitchecken (' . $registration->checked_out_at->format('H:i') . ').',
                    'errors'  => ['event_time' => ['Het startuur ligt na het uitchecken.']],
                ], 422);
            }

            $data['checked_in_at'] = $newStart;
            $startChanged = true;

            if ($registration->checked_out_at) {
                $data['played_minutes'] = max(0, (int) $newStart->diffInMinutes($registration->checked_out_at));
            }
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

        $subscriberIds = $request->has('subscriber_membership_ids')
            ? array_map('intval', $data['subscriber_membership_ids'] ?? [])
            : null;
        unset($data['subscriber_membership_ids']);

        DB::transaction(function () use ($registration, $data, $subscriberIds, $currentTenant) {
            $registration->update($data);

            if ($subscriberIds !== null) {
                $this->syncSubscribers($registration, $subscriberIds, $currentTenant);
            }
        });

        $registration->load($this->withRelations());

        // Al uitgecheckt maar nog niet betaald: speeltijd opnieuw berekenen met het nieuwe startuur
        if ($startChanged && $registration->status === Registration::STATUS_CHECKED_OUT) {
            $hasOpenOrder = Order::query()
                ->where('registration_id', $registration->id)
                ->where('status', Order::STATUS_OPEN)
                ->exists();

            if ($hasOpenOrder || ! $registration->is_member) {
                $this->orderService->syncPricingForRegistration($registration, $currentTenant);
                $registration->refresh()->load($this->withRelations());
            }
        }

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

        $order    = null;
        $autoPaid = false;
        $hasSubscribers = $registration->is_member || $registration->subscribers->isNotEmpty();

        if (! $hasSubscribers) {
            $order = $this->orderService->syncPricingForRegistration($registration, $currentTenant);
        } else {
            // Abonnees: speeltijd is gratis, catering en extra's niet.
            $openOrder = Order::query()
                ->where('tenant_id', $currentTenant->id())
                ->where('registration_id', $registration->id)
                ->where('status', Order::STATUS_OPEN)
                ->latest('id')
                ->first();

            $pricingLines = app(PricingEvaluator::class)
                ->evaluate(PricingContext::fromRegistration($registration))
                ->lines;

            if ($pricingLines->isNotEmpty() || $openOrder) {
                $order = $this->orderService->syncPricingForRegistration($registration, $currentTenant);
            }

            // Enkel gespeeld, niets extra gekocht → meteen op betaald
            if (! $order || $order->items->isEmpty()) {
                DB::transaction(function () use ($order, $registration, $actorUserId) {
                    if ($order && ! $order->items()->exists()) {
                        $order->delete(); // leeg open order opruimen
                    }

                    $registration->update([
                        'status'           => Registration::STATUS_PAID,
                        'bill_total_cents' => 0,
                        'updated_by'       => $actorUserId,
                    ]);
                });

                $order    = null;
                $autoPaid = true;
                $registration->load($this->withRelations());
            }
        }

        return response()->json([
            'message'   => $autoPaid ? 'Uitgecheckt en betaald (abonnement, niets extra gekocht).' : 'Registratie uitgecheckt.',
            'data'      => $this->transformRegistration($registration),
            'order'     => $order ? $this->transformOrderForPos($order) : null,
            'auto_paid' => $autoPaid,
        ]);
    }

    // ------------------------------------------------------------------
    // Vroegtijdig stoppen
    // ------------------------------------------------------------------

    /**
     * Eén of meer deelnemers stoppen vroeger. Ze blijven op de groepsrekening
     * maar betalen speeltijd tot stopped_at.
     */
    public function storeEarlyStop(Request $request, Registration $registration, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);

        $data = $request->validate([
            'participants_children'      => ['required', 'integer', 'min:0'],
            'participants_adults'        => ['required', 'integer', 'min:0'],
            'registration_subscriber_id' => ['nullable', 'integer'],
            'stopped_at'                 => ['nullable', 'string', 'max:30'], // HH:MM (vandaag) of datum/tijd
        ]);

        if ($registration->status !== Registration::STATUS_CHECKED_IN || ! $registration->checked_in_at) {
            return response()->json(['message' => 'Enkel bij een ingecheckte registratie kan iemand stoppen.'], 422);
        }

        $registration->load(['earlyStops', 'subscribers']);

        $children = (int) $data['participants_children'];
        $adults   = (int) $data['participants_adults'];

        if ($children + $adults < 1) {
            return response()->json(['message' => 'Kies wie er stopt.'], 422);
        }

        $playingChildren = (int) $registration->participants_children - (int) $registration->earlyStops->sum('participants_children');
        $playingAdults   = (int) $registration->participants_adults - (int) $registration->earlyStops->sum('participants_adults');

        if ($children > $playingChildren || $adults > $playingAdults) {
            return response()->json(['message' => 'Er stoppen meer deelnemers dan er nog spelen.'], 422);
        }

        if (($playingChildren - $children) + ($playingAdults - $adults) < 1) {
            return response()->json(['message' => 'Dan stopt de hele groep: gebruik Uitchecken.'], 422);
        }

        // Abonnee die stopt
        $subscriberId = $data['registration_subscriber_id'] ?? null;

        if ($subscriberId) {
            $subscriber = $registration->subscribers->firstWhere('id', (int) $subscriberId);

            if (! $subscriber) {
                return response()->json(['message' => 'Deze abonnee hoort niet bij deze groep.'], 422);
            }

            if ($registration->earlyStops->contains('registration_subscriber_id', $subscriber->id)) {
                return response()->json(['message' => 'Deze abonnee is al gestopt.'], 422);
            }

            $inScope = $subscriber->participant_scope === RegistrationSubscriber::SCOPE_CHILDREN ? $children : $adults;

            if ($inScope < 1) {
                return response()->json([
                    'message' => $subscriber->participant_scope === RegistrationSubscriber::SCOPE_CHILDREN
                        ? 'Deze abonnee telt als kind/student: zet minstens 1 kind op stoppen.'
                        : 'Deze abonnee telt als volwassene: zet minstens 1 volwassene op stoppen.',
                ], 422);
            }
        }

        $stoppedAt = $this->resolveStoppedAt($data['stopped_at'] ?? null, $registration);

        if ($stoppedAt->lessThan($registration->checked_in_at)) {
            return response()->json(['message' => 'Het stoptijdstip ligt vóór het inchecken (' . $registration->checked_in_at->format('H:i') . ').'], 422);
        }

        if ($stoppedAt->greaterThan(now()->addMinute())) {
            return response()->json(['message' => 'Het stoptijdstip ligt in de toekomst.'], 422);
        }

        $registration->earlyStops()->create([
            'tenant_id'                  => $registration->tenant_id,
            'participants_children'      => $children,
            'participants_adults'        => $adults,
            'registration_subscriber_id' => $subscriberId ? (int) $subscriberId : null,
            'stopped_at'                 => $stoppedAt,
            'created_by'                 => $this->frontdeskUserId($request),
        ]);

        $registration->unsetRelation('earlyStops');
        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Gestopt geregistreerd.',
            'data'    => $this->transformRegistration($registration),
        ], 201);
    }

    public function destroyEarlyStop(Registration $registration, RegistrationEarlyStop $earlyStop, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless((int) $registration->tenant_id === (int) $currentTenant->id(), 404);
        abort_unless((int) $earlyStop->registration_id === (int) $registration->id, 404);

        if ($registration->status !== Registration::STATUS_CHECKED_IN) {
            return response()->json(['message' => 'Ongedaan maken kan enkel zolang de groep ingecheckt is.'], 422);
        }

        $earlyStop->delete();
        $registration->load($this->withRelations());

        return response()->json([
            'message' => 'Stop ongedaan gemaakt.',
            'data'    => $this->transformRegistration($registration),
        ]);
    }

    /**
     * Startuur uit het formulier als inchecktijd: enkel als de datum vandaag is
     * en het uur niet in de toekomst ligt. Anders null (→ nu).
     */
    private function startFromForm(mixed $date, ?string $time): ?Carbon
    {
        if (! $time || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        $day = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        if ($day !== now()->toDateString()) {
            return null;
        }

        $start = Carbon::parse("{$day} {$time}");

        return $start->greaterThan(now()) ? null : $start;
    }

    private function resolveStoppedAt(?string $value, Registration $registration): Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return now();
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            $date = $registration->checked_in_at?->toDateString() ?? now()->toDateString();

            return Carbon::parse("{$date} {$value}");
        }

        return Carbon::parse($value);
    }

    protected function transformEarlyStops(Registration $registration): array
    {
        $checkedInAt = $registration->checked_in_at;

        return $registration->earlyStops
            ->map(function (RegistrationEarlyStop $stop) use ($checkedInAt) {
                $account = $stop->subscriber?->tenantMembership?->account;

                return [
                    'id'                         => $stop->id,
                    'participants_children'      => (int) $stop->participants_children,
                    'participants_adults'        => (int) $stop->participants_adults,
                    'registration_subscriber_id' => $stop->registration_subscriber_id,
                    'subscriber_name'            => $account ? trim($account->first_name . ' ' . $account->last_name) : null,
                    'stopped_at'                 => $stop->stopped_at?->toIso8601String(),
                    'stopped_time'               => $stop->stopped_at?->format('H:i'),
                    'played_minutes'             => $checkedInAt && $stop->stopped_at
                        ? max(0, (int) $checkedInAt->diffInMinutes($stop->stopped_at))
                        : null,
                ];
            })
            ->values()
            ->all();
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
            'subscribers'           => $this->transformSubscribers($registration),
            'subscriber_count'      => $registration->subscribers->count(),
            'subscribers_children'  => $registration->subscribers->where('participant_scope', RegistrationSubscriber::SCOPE_CHILDREN)->count(),
            'subscribers_adults'    => $registration->subscribers->where('participant_scope', RegistrationSubscriber::SCOPE_ADULTS)->count(),
            'early_stops'           => $this->transformEarlyStops($registration),
            'stopped_children'      => (int) $registration->earlyStops->sum('participants_children'),
            'stopped_adults'        => (int) $registration->earlyStops->sum('participants_adults'),
            'duration_label'        => $registration->stayOption?->name,
            'event_type'            => $registration->eventTypeLabel(), // + met/zonder verjaardagsformule
            'birthday_formula'      => $registration->birthday_formula,
            'catering_option'       => $registration->cateringOption?->name,
            'event_type_code'       => $registration->eventType?->code,
            'catering_option_code'  => $registration->cateringOption?->code,
            'stay_duration_minutes' => $registration->stayOption?->duration_minutes,
            'event_type_emoji'      => $registration->eventType?->emoji,
            'catering_option_emoji' => $registration->cateringOption?->emoji,
        ];
    }

    protected function transformSubscribers(Registration $registration): array
    {
        $date = $registration->event_date
            ? Carbon::parse($registration->event_date)->startOfDay()
            : now()->startOfDay();

        return $registration->subscribers
            ->map(function (RegistrationSubscriber $subscriber) use ($date) {
                $membership = $subscriber->tenantMembership;

                if ($membership) {
                    return [
                        ...MemberController::mapSubscriberCandidate($membership, $date),
                        'id'                => $subscriber->id,
                        'participant_scope' => $subscriber->participant_scope,
                    ];
                }

                // Abonnement intussen verwijderd: toon wat we nog weten
                return [
                    'id'                   => $subscriber->id,
                    'tenant_membership_id' => null,
                    'account_id'           => $subscriber->account_id,
                    'full_name'            => 'Verwijderd abonnement',
                    'email'                => null,
                    'membership_type'      => null,
                    'participant_scope'    => $subscriber->participant_scope,
                    'membership_ends_at'   => null,
                    'is_active'            => false,
                    'is_valid'             => false,
                    'expires_soon'         => false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Zet de abonnees van een registratie gelijk aan de opgegeven lijst
     * (tenant_membership ids) en leidt is_member/account_id af.
     *
     * - nieuw toegevoegde abonnees moeten een actief abonnement hebben op de bezoekdatum
     * - per categorie nooit meer abonnees dan deelnemers
     * - is_member = true als er geen betalende deelnemers overblijven
     */
    protected function syncSubscribers(Registration $registration, array $membershipIds, CurrentTenant $currentTenant): void
    {
        $membershipIds = array_values(array_unique(array_filter($membershipIds)));

        $memberships = TenantMembership::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('id', $membershipIds)
            ->get()
            ->keyBy('id');

        if ($memberships->count() !== count($membershipIds)) {
            throw ValidationException::withMessages([
                'subscriber_membership_ids' => 'Eén of meer abonnees werden niet gevonden.',
            ]);
        }

        $existing = $registration->subscribers()->get();
        $existingIds = $existing->pluck('tenant_membership_id')->filter()->map(fn ($id) => (int) $id)->all();

        $date = $registration->event_date
            ? Carbon::parse($registration->event_date)->startOfDay()
            : now()->startOfDay();

        foreach ($membershipIds as $id) {
            if (in_array($id, $existingIds, true)) {
                continue; // bestaande abonnee: niet opnieuw valideren
            }

            /** @var TenantMembership $m */
            $m = $memberships->get($id);
            $endsAt = $m->membership_ends_at?->copy()->startOfDay();

            if (! $m->is_active || ! $endsAt || $endsAt->lt($date)) {
                throw ValidationException::withMessages([
                    'subscriber_membership_ids' => sprintf('%s heeft geen actief abonnement op %s.', $m->full_name, $date->format('d/m/Y')),
                ]);
            }
        }

        // Categorie per abonnee: bestaande rijen behouden hun categorie
        $scopes = [];
        foreach ($membershipIds as $id) {
            $row = $existing->firstWhere('tenant_membership_id', $id);
            $scopes[$id] = $row?->participant_scope
                ?? RegistrationSubscriber::scopeForMembershipType($memberships->get($id)->membership_type);
        }

        $childrenSubs = count(array_filter($scopes, fn ($s) => $s === RegistrationSubscriber::SCOPE_CHILDREN));
        $adultsSubs   = count(array_filter($scopes, fn ($s) => $s === RegistrationSubscriber::SCOPE_ADULTS));
        $children     = (int) $registration->participants_children;
        $adults       = (int) $registration->participants_adults;

        if ($childrenSubs > $children || $adultsSubs > $adults) {
            throw ValidationException::withMessages([
                'subscriber_membership_ids' => 'Er zijn meer abonnees dan deelnemers in een categorie. Pas de aantallen aan.',
            ]);
        }

        // Sync
        $registration->subscribers()
            ->where(fn ($q) => $q
                ->whereNull('tenant_membership_id')
                ->orWhereNotIn('tenant_membership_id', $membershipIds ?: [0])
            )
            ->delete();

        foreach ($membershipIds as $id) {
            if (in_array($id, $existingIds, true)) {
                continue;
            }

            $m = $memberships->get($id);

            $registration->subscribers()->create([
                'tenant_id'            => $registration->tenant_id,
                'tenant_membership_id' => $m->id,
                'account_id'           => $m->account_id,
                'participant_scope'    => $scopes[$id],
            ]);
        }

        // Afgeleide velden
        $paying = max(0, $children - $childrenSubs) + max(0, $adults - $adultsSubs);
        $hasSubscribers = count($membershipIds) > 0;

        $registration->is_member = $hasSubscribers && $paying === 0;

        if ($hasSubscribers && ! $registration->account_id) {
            $registration->account_id = $memberships->get($membershipIds[0])->account_id;
        }

        $registration->save();
        $registration->unsetRelation('subscribers');
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

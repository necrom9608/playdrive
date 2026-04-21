<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\BookingFormConfig;
use App\Models\Registration;
use App\Services\ReservationMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberReservationController extends Controller
{
    /** Statussen waarbij aanpassen/annuleren is toegelaten. */
    private const EDITABLE_STATUSES = [
        Registration::STATUS_NEW,
        Registration::STATUS_PENDING,
        Registration::STATUS_CONFIRMED,
    ];

    /**
     * Lijst van alle reservaties voor het ingelogde e-mailadres.
     * Geannuleerd en no-show worden standaard niet meegegeven
     * tenzij ?include_cancelled=1 meegegeven wordt.
     */
    public function index(Request $request): JsonResponse
    {
        $email = strtolower(trim($request->user()->email));

        $query = Registration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->with([
                'tenant:id,name,slug,phone,email',
                'eventType:id,name,emoji',
                'stayOption:id,name',
            ])
            ->orderByDesc('event_date')
            ->orderByDesc('created_at');

        if (! $request->boolean('include_cancelled')) {
            $query->whereNotIn('status', [
                Registration::STATUS_CANCELLED,
                Registration::STATUS_NO_SHOW,
            ]);
        }

        return response()->json([
            'data' => $query->get()->map(fn ($r) => $this->transform($r)),
        ]);
    }

    /**
     * Detail van één reservatie — alleen als het e-mailadres overeenkomt.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $registration = $this->findForUser($request, $id);

        return response()->json(['data' => $this->transformDetail($registration)]);
    }

    /**
     * Aanpassen van aantallen en/of commentaar.
     * Alleen toegelaten als status in EDITABLE_STATUSES zit.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $registration = $this->findForUser($request, $id);

        if (! in_array($registration->status, self::EDITABLE_STATUSES)) {
            return response()->json([
                'message' => 'Deze reservatie kan niet meer worden aangepast.',
            ], 422);
        }

        $data = $request->validate([
            'participants_children'    => ['nullable', 'integer', 'min:0'],
            'participants_adults'      => ['nullable', 'integer', 'min:0'],
            'participants_supervisors' => ['nullable', 'integer', 'min:0'],
            'comment'                  => ['nullable', 'string', 'max:2000'],
        ]);

        // Bereken totaal na wijziging
        $children    = $data['participants_children']    ?? $registration->participants_children;
        $adults      = $data['participants_adults']      ?? $registration->participants_adults;
        $supervisors = $data['participants_supervisors'] ?? $registration->participants_supervisors;

        if (($children + $adults + $supervisors) < 1) {
            return response()->json([
                'message' => 'Er moet minstens 1 deelnemer opgegeven worden.',
                'errors'  => ['participants_total' => ['Er moet minstens 1 deelnemer zijn.']],
            ], 422);
        }

        $registration->fill([
            'participants_children'    => $children,
            'participants_adults'      => $adults,
            'participants_supervisors' => $supervisors,
            'comment'                  => array_key_exists('comment', $data)
                                              ? $data['comment']
                                              : $registration->comment,
        ]);
        $registration->save();

        // Bevestigingsmail
        try {
            $tenant = $registration->tenant;
            if ($tenant) {
                ReservationMailService::sendAfterUpdate($registration, $tenant);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Update-mail mislukt', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return response()->json(['data' => $this->transformDetail($registration->fresh([
            'tenant:id,name,slug,phone,email',
            'eventType:id,name,emoji',
            'stayOption:id,name',
            'cateringOption:id,name',
        ]))]);
    }

    /**
     * Annuleren — alleen als status in EDITABLE_STATUSES zit
     * en de annuleringsdeadline nog niet verstreken is.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $registration = $this->findForUser($request, $id);

        if (! in_array($registration->status, self::EDITABLE_STATUSES)) {
            return response()->json([
                'message' => 'Deze reservatie kan niet meer worden geannuleerd.',
            ], 422);
        }

        // Deadline check
        if ($registration->event_date && $registration->event_time) {
            $hoursAllowed = $this->cancellationHoursFor($registration);

            $eventDateTime = \Carbon\Carbon::parse(
                $registration->event_date->format('Y-m-d') . ' ' . $registration->event_time
            );
            $hoursUntilEvent = now()->diffInHours($eventDateTime, false);

            if ($hoursUntilEvent < $hoursAllowed) {
                return response()->json([
                    'message' => "Annuleren is enkel mogelijk tot {$hoursAllowed} uur voor het event.",
                ], 422);
            }
        }

        $registration->update([
            'status'       => Registration::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        // Bevestigingsmail
        try {
            $tenant = $registration->tenant;
            if ($tenant) {
                ReservationMailService::sendAfterCancellation($registration, $tenant);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Annulering-mail mislukt', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Reservatie geannuleerd.']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function findForUser(Request $request, int $id): Registration
    {
        $email = strtolower(trim($request->user()->email));

        return Registration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->with([
                'tenant:id,name,slug,phone,email',
                'eventType:id,name,emoji',
                'stayOption:id,name',
                'cateringOption:id,name',
            ])
            ->findOrFail($id);
    }

    private function cancellationHoursFor(Registration $registration): int
    {
        $config = BookingFormConfig::query()
            ->where('tenant_id', $registration->tenant_id)
            ->first();

        return (int) ($config?->cancellation_hours_before ?? 24);
    }

    private function transform(Registration $r): array
    {
        return [
            'id'          => $r->id,
            'status'      => $r->status,
            'event_date'  => $r->event_date?->format('Y-m-d'),
            'event_time'  => $r->event_time ? substr((string) $r->event_time, 0, 5) : null,
            'event_type'  => $r->eventType?->name,
            'event_emoji' => $r->eventType?->emoji,
            'stay_option' => $r->stayOption?->name,
            'total_count' => $r->total_participants,
            'tenant_name' => $r->tenant?->display_name ?? $r->tenant?->name,
            'tenant_slug' => $r->tenant?->slug,
            'can_edit'    => in_array($r->status, self::EDITABLE_STATUSES),
        ];
    }

    private function transformDetail(Registration $r): array
    {
        return array_merge($this->transform($r), [
            'name'                    => $r->name,
            'phone'                   => $r->phone,
            'email'                   => $r->email,
            'catering_option'         => $r->cateringOption?->name,
            'participants_children'   => (int) $r->participants_children,
            'participants_adults'     => (int) $r->participants_adults,
            'participants_supervisors'=> (int) $r->participants_supervisors,
            'comment'                 => $r->comment,
            'outside_opening_hours'   => (bool) $r->outside_opening_hours,
            'invoice_requested'       => (bool) $r->invoice_requested,
            'invoice_company_name'    => $r->invoice_company_name,
            'tenant_phone'            => $r->tenant?->phone,
            'tenant_email'            => $r->tenant?->email,
            'tenant_address'          => $r->tenant?->full_address,
            'created_at'              => $r->created_at?->toIso8601String(),
        ]);
    }
}

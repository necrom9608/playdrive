<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberReservationController extends Controller
{
    /**
     * Geeft alle reservaties terug die gekoppeld zijn aan het e-mailadres
     * van de ingelogde account — ook reservaties die gemaakt werden zonder
     * in te loggen worden zo zichtbaar.
     */
    public function index(Request $request): JsonResponse
    {
        $email = strtolower(trim($request->user()->email));

        $registrations = Registration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->whereNotIn('status', [
                Registration::STATUS_CANCELLED,
                Registration::STATUS_NO_SHOW,
            ])
            ->with([
                'tenant:id,name,slug,phone,email',
                'eventType:id,name,emoji',
                'stayOption:id,name',
            ])
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $registrations->map(fn ($r) => $this->transform($r)),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $email = strtolower(trim($request->user()->email));

        $registration = Registration::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->with([
                'tenant:id,name,slug,phone,email,street,number,postal_code,city',
                'eventType:id,name,emoji',
                'stayOption:id,name',
                'cateringOption:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->transformDetail($registration),
        ]);
    }

    // ------------------------------------------------------------------

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

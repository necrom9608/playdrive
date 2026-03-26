<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $registrations = Registration::query()
            ->with(['eventType:id,name,code', 'stayOption:id,name,code,duration_minutes', 'cateringOption:id,name,code'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (Registration $registration) {
                return [
                    'id' => $registration->id,
                    'name' => $registration->name,
                    'phone' => $registration->phone,
                    'email' => $registration->email,
                    'postal_code' => $registration->postal_code,
                    'municipality' => $registration->municipality,
                    'event_type_id' => $registration->event_type_id,
                    'event_date' => optional($registration->event_date)->format('Y-m-d'),
                    'event_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
                    'stay_option_id' => $registration->stay_option_id,
                    'catering_option_id' => $registration->catering_option_id,
                    'participants_children' => $registration->participants_children,
                    'participants_adults' => $registration->participants_adults,
                    'participants_supervisors' => $registration->participants_supervisors,
                    'total_count' => $registration->total_participants,
                    'comment' => $registration->comment,
                    'stats' => $registration->stats ?? [],
                    'status' => $registration->status,
                    'invoice_requested' => $registration->invoice_requested,
                    'invoice_company_name' => $registration->invoice_company_name,
                    'invoice_vat_number' => $registration->invoice_vat_number,
                    'invoice_email' => $registration->invoice_email,
                    'invoice_address' => $registration->invoice_address,
                    'invoice_postal_code' => $registration->invoice_postal_code,
                    'invoice_city' => $registration->invoice_city,
                    'checked_in_at' => optional($registration->checked_in_at)?->toDateTimeString(),
                    'checked_out_at' => optional($registration->checked_out_at)?->toDateTimeString(),
                    'outside_opening_hours' => $registration->outside_opening_hours,
                    'duration_label' => $registration->stayOption?->name,
                    'event_type' => $registration->eventType?->name,
                    'catering_option' => $registration->cateringOption?->name,
                ];
            });

        return response()->json([
            'data' => $registrations,
        ]);
    }

    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $registration = Registration::create($request->validated());

        $registration->load([
            'eventType:id,name,code',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code',
        ]);

        return response()->json([
            'message' => 'Registratie opgeslagen.',
            'data' => [
                'id' => $registration->id,
                'name' => $registration->name,
                'phone' => $registration->phone,
                'email' => $registration->email,
                'postal_code' => $registration->postal_code,
                'municipality' => $registration->municipality,
                'event_type_id' => $registration->event_type_id,
                'event_date' => optional($registration->event_date)->format('Y-m-d'),
                'event_time' => $registration->event_time ? substr((string) $registration->event_time, 0, 5) : null,
                'stay_option_id' => $registration->stay_option_id,
                'catering_option_id' => $registration->catering_option_id,
                'participants_children' => $registration->participants_children,
                'participants_adults' => $registration->participants_adults,
                'participants_supervisors' => $registration->participants_supervisors,
                'total_count' => $registration->total_participants,
                'comment' => $registration->comment,
                'stats' => $registration->stats ?? [],
                'status' => $registration->status,
                'invoice_requested' => $registration->invoice_requested,
                'invoice_company_name' => $registration->invoice_company_name,
                'invoice_vat_number' => $registration->invoice_vat_number,
                'invoice_email' => $registration->invoice_email,
                'invoice_address' => $registration->invoice_address,
                'invoice_postal_code' => $registration->invoice_postal_code,
                'invoice_city' => $registration->invoice_city,
                'checked_in_at' => optional($registration->checked_in_at)?->toDateTimeString(),
                'checked_out_at' => optional($registration->checked_out_at)?->toDateTimeString(),
                'outside_opening_hours' => $registration->outside_opening_hours,
                'duration_label' => $registration->stayOption?->name,
                'event_type' => $registration->eventType?->name,
                'catering_option' => $registration->cateringOption?->name,
            ],
        ], 201);
    }
}

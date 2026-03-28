<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\StoreRegistrationRequest;
use App\Models\Registration;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $query = Registration::query()
            ->with([
                'eventType:id,name,code,emoji',
                'stayOption:id,name,code,duration_minutes',
                'cateringOption:id,name,code,emoji',
            ])
            ->latest('id')
            ->limit(100);

        if ($currentTenant->exists()) {
            $query->where('tenant_id', $currentTenant->id());
        }

        $registrations = $query
            ->get()
            ->map(fn (Registration $registration) => $this->transformRegistration($registration));

        return response()->json([
            'data' => $registrations,
        ]);
    }

    public function store(StoreRegistrationRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validated();

        if ($currentTenant->exists()) {
            $data['tenant_id'] = $currentTenant->id();
        }

        if (($data['status'] ?? null) === Registration::STATUS_CHECKED_IN && empty($data['checked_in_at'])) {
            $data['checked_in_at'] = now();
        }

        $registration = Registration::create($data);

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie opgeslagen.',
            'data' => $this->transformRegistration($registration),
        ], 201);
    }

    public function update(
        StoreRegistrationRequest $request,
        Registration $registration
    ): JsonResponse {
        $data = $request->validated();

        if (($data['status'] ?? null) === Registration::STATUS_CHECKED_IN && ! $registration->checked_in_at) {
            $data['checked_in_at'] = now();
        }

        $registration->update($data);

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie bijgewerkt.',
            'data' => $this->transformRegistration($registration),
        ]);
    }

    public function checkIn(Registration $registration): JsonResponse
    {
        $registration->status = Registration::STATUS_CHECKED_IN;
        $registration->checked_in_at = now();
        $registration->save();

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie ingecheckt.',
            'data' => $this->transformRegistration($registration),
        ]);
    }

    public function checkOut(Registration $registration): JsonResponse
    {
        DB::transaction(function () use ($registration) {
            $registration->status = Registration::STATUS_CHECKED_OUT;
            $registration->checked_out_at = now();

            if ($registration->checked_in_at) {
                $registration->played_minutes = max(
                    0,
                    $registration->checked_in_at->diffInMinutes($registration->checked_out_at)
                );
            }

            $registration->save();
        });

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie uitgecheckt.',
            'data' => $this->transformRegistration($registration),
        ]);
    }

    public function cancel(Registration $registration): JsonResponse
    {
        $registration->status = Registration::STATUS_CANCELLED;
        $registration->save();

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie geannuleerd.',
            'data' => $this->transformRegistration($registration),
        ]);
    }

    public function noShow(Registration $registration): JsonResponse
    {
        $registration->status = Registration::STATUS_NO_SHOW;
        $registration->save();

        $registration->load([
            'eventType:id,name,code,emoji',
            'stayOption:id,name,code,duration_minutes',
            'cateringOption:id,name,code,emoji',
        ]);

        return response()->json([
            'message' => 'Registratie op no-show gezet.',
            'data' => $this->transformRegistration($registration),
        ]);
    }

    public function destroy(Registration $registration): JsonResponse
    {
        $registration->delete();

        return response()->json([
            'message' => 'Registratie verwijderd.',
        ]);
    }

    protected function transformRegistration(Registration $registration): array
    {
        return [
            'id' => $registration->id,
            'tenant_id' => $registration->tenant_id,
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
            'checked_in_at' => optional($registration->checked_in_at)?->toIso8601String(),
            'checked_out_at' => optional($registration->checked_out_at)?->toIso8601String(),
            'created_at' => optional($registration->created_at)?->toIso8601String(),
            'updated_at' => optional($registration->updated_at)?->toIso8601String(),
            'played_minutes' => $registration->played_minutes,
            'outside_opening_hours' => $registration->outside_opening_hours,
            'duration_label' => $registration->stayOption?->name,
            'event_type' => $registration->eventType?->name,
            'catering_option' => $registration->cateringOption?->name,
            'event_type_code' => $registration->eventType?->code,
            'catering_option_code' => $registration->cateringOption?->code,
            'stay_duration_minutes' => $registration->stayOption?->duration_minutes,
            'event_type_emoji' => $registration->eventType?->emoji,
            'catering_option_emoji' => $registration->cateringOption?->emoji,
        ];
    }
}

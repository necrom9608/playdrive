<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RegistrationAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReservationController extends Controller
{
    /**
     * Geeft reservatiedetails terug op basis van een magic link token.
     * Publiek toegankelijk — geen authenticatie vereist.
     * Retourneert 410 Gone als de token verlopen is.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $accessToken = RegistrationAccessToken::query()
            ->with([
                'registration.eventType:id,name,emoji',
                'registration.stayOption:id,name',
                'registration.cateringOption:id,name',
                'registration.account:id,email',
            ])
            ->where('token', $token)
            ->first();

        if (! $accessToken) {
            return response()->json(['message' => 'Token niet gevonden.'], 404);
        }

        if ($accessToken->isExpired()) {
            return response()->json(['message' => 'Token verlopen.'], 410);
        }

        $registration = $accessToken->registration;
        $tenant       = $registration->tenant ?? null;

        // Bepaal of er al een account is voor dit e-mailadres
        $hasAccount = false;
        if (filled($registration->email)) {
            $hasAccount = Account::query()
                ->where('email', strtolower($registration->email))
                ->exists();
        }

        return response()->json([
            'data' => [
                'id'                => $registration->id,
                'name'              => $registration->name,
                'email'             => $registration->email,
                'event_date'        => $registration->event_date?->format('Y-m-d'),
                'event_time'        => $registration->event_time
                                           ? substr((string) $registration->event_time, 0, 5)
                                           : null,
                'event_type'        => $registration->eventType?->name,
                'stay_option'       => $registration->stayOption?->name,
                'catering_option'   => $registration->cateringOption?->name,
                'participants_children'   => (int) $registration->participants_children,
                'participants_adults'     => (int) $registration->participants_adults,
                'participants_supervisors'=> (int) $registration->participants_supervisors,
                'total_count'       => $registration->total_participants,
                'status'            => $registration->status,
                'comment'           => $registration->comment,
                'tenant_name'       => $tenant?->display_name,
                'tenant_slug'       => $tenant?->slug,
                'tenant_phone'      => $tenant?->phone,
                'tenant_email'      => $tenant?->email,
                'has_account'       => $hasAccount,
            ],
        ]);
    }
}

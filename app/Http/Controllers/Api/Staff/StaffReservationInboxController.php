<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\ReservationMailService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReservationInboxController extends Controller
{
    /**
     * Geeft alle new + pending reservaties terug voor de tenant.
     * Alleen toegankelijk voor admins.
     */
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $registrations = Registration::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('status', [
                Registration::STATUS_NEW,
                Registration::STATUS_PENDING,
            ])
            ->with([
                'eventType:id,name,emoji',
                'stayOption:id,name',
            ])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $registrations->map(fn ($r) => $this->transform($r)),
        ]);
    }

    /**
     * Bevestigt een reservatie (new of pending → confirmed).
     * Stuurt de bevestigingsmail naar de klant.
     * Alleen toegankelijk voor admins.
     */
    public function confirm(Request $request, CurrentTenant $currentTenant, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $registration = Registration::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('status', [
                Registration::STATUS_NEW,
                Registration::STATUS_PENDING,
            ])
            ->with([
                'eventType:id,name,emoji',
                'stayOption:id,name',
                'cateringOption:id,name',
            ])
            ->findOrFail($id);

        $wasPending = $registration->status === Registration::STATUS_PENDING;

        $registration->update([
            'status' => Registration::STATUS_CONFIRMED,
        ]);

        // Enkel voor pending een extra mail sturen — bij new is de bevestiging al verstuurd
        if ($wasPending) {
            try {
                $tenant = $currentTenant->tenant;
                // Klant krijgt nu de gewone bevestiging; de zaak kreeg de melding al bij de aanvraag
                ReservationMailService::sendAfterSubmission($registration->fresh([
                    'eventType:id,name,emoji',
                    'stayOption:id,name',
                    'cateringOption:id,name',
                ]), $tenant, notifyTenant: false);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Bevestigingsmail (staff confirm pending) mislukt', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Reservatie bevestigd.',
            'data'    => $this->transform($registration),
        ]);
    }

    /**
     * Weigert een reservatie (new of pending → cancelled).
     * Er gaat géén mail naar de klant — de zaak contacteert de klant zelf.
     * De reservatie blijft bewaard (historiek) maar verdwijnt uit de inbox.
     */
    public function reject(Request $request, CurrentTenant $currentTenant, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $registration = $this->findOpen($currentTenant, $id);
        $userId = $request->attributes->get('staff_user')->id;

        $registration->status       = Registration::STATUS_CANCELLED;
        $registration->cancelled_at = now();
        $registration->cancelled_by = $userId;
        $registration->updated_by   = $userId;
        $registration->save();

        return response()->json(['message' => 'Reservatie geweigerd.']);
    }

    /**
     * Verwijdert een reservatie definitief (bv. dubbel: al manueel ingegeven).
     */
    public function destroy(Request $request, CurrentTenant $currentTenant, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $registration = $this->findOpen($currentTenant, $id);
        $registration->delete();

        return response()->json(['message' => 'Reservatie verwijderd.']);
    }

    // ------------------------------------------------------------------

    private function findOpen(CurrentTenant $currentTenant, int $id): Registration
    {
        return Registration::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereIn('status', [
                Registration::STATUS_NEW,
                Registration::STATUS_PENDING,
            ])
            ->findOrFail($id);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->attributes->get('staff_user');
        abort_unless($user && (bool) $user->is_admin, 403, 'Enkel admins hebben toegang tot deze functie.');
    }

    private function transform(Registration $r): array
    {
        return [
            'id'                    => $r->id,
            'status'                => $r->status,
            'name'                  => $r->name,
            'email'                 => $r->email,
            'phone'                 => $r->phone,
            'event_date'            => $r->event_date?->format('Y-m-d'),
            'event_time'            => $r->event_time ? substr((string) $r->event_time, 0, 5) : null,
            'event_type'            => $r->eventTypeLabel(), // + met/zonder verjaardagsformule
            'event_emoji'           => $r->eventType?->emoji,
            'stay_option'           => $r->stayOption?->name,
            'participants_children' => (int) $r->participants_children,
            'participants_adults'   => (int) $r->participants_adults,
            'participants_supervisors' => (int) $r->participants_supervisors,
            'total_count'           => $r->total_participants,
            'outside_opening_hours' => (bool) $r->outside_opening_hours,
            'comment'               => $r->comment,
            'created_at'            => $r->created_at?->toIso8601String(),
        ];
    }
}

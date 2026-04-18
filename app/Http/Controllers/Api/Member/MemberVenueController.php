<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberVenueController extends Controller
{
    /**
     * Alle venues waaraan de ingelogde gebruiker gekoppeld is.
     */
    public function index(Request $request): JsonResponse
    {
        $memberships = TenantMembership::query()
            ->where('account_id', $request->user()->id)
            ->with('tenant')
            ->get();

        return response()->json([
            'data' => $memberships->map(fn ($m) => $this->venueData($m)),
        ]);
    }

    /**
     * Alle actieve venues — publiek, voor de discovery lijst.
     */
    public function discover(): JsonResponse
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('company_name')
            ->get();

        return response()->json([
            'data' => $tenants->map(fn ($t) => [
                'slug'     => $t->slug,
                'name'     => $t->display_name,
                'city'     => $t->city,
                'address'  => $t->full_address,
                'logo_url' => $t->logo_url,
            ]),
        ]);
    }

    /**
     * Publieke info van één venue — geen auth vereist.
     */
    public function show(string $slug): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'slug'        => $tenant->slug,
                'name'        => $tenant->display_name,
                'logo_url'    => $tenant->logo_url,
                'address'     => $tenant->full_address,
                'city'        => $tenant->city,
                'phone'       => $tenant->phone,
                'email'       => $tenant->email,
            ],
        ]);
    }

    /**
     * Koppel de ingelogde gebruiker aan een venue.
     * Idempotent: als de koppeling al bestaat, gewoon 200 teruggeven.
     */
    public function join(Request $request, string $slug): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $existing = TenantMembership::query()
            ->where('account_id', $request->user()->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Je bent al gekoppeld aan deze venue.',
                'data'    => $this->venueData($existing->load('tenant')),
            ]);
        }

        TenantMembership::query()->create([
            'account_id' => $request->user()->id,
            'tenant_id'  => $tenant->id,
            'is_active'  => false,
        ]);

        return response()->json([
            'message' => "Je bent nu gekoppeld aan {$tenant->display_name}.",
            'data'    => [
                'slug'              => $tenant->slug,
                'name'              => $tenant->display_name,
                'membership_status' => 'none',
            ],
        ], 201);
    }

    /**
     * Lidmaatschapsstatus voor de ingelogde gebruiker bij een specifieke venue.
     */
    public function membership(Request $request, string $slug): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $membership = TenantMembership::query()
            ->where('account_id', $request->user()->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Je bent niet gekoppeld aan deze venue.',
            ], 404);
        }

        $status = $this->resolveStatus($membership);

        return response()->json([
            'data' => [
                'status'          => $status,
                'membership_type' => $membership->membership_type,
                'starts_at'       => $membership->membership_starts_at?->toDateString(),
                'ends_at'         => $membership->membership_ends_at?->toDateString(),
                'qr_token'        => $status === 'active' ? $membership->rfid_uid : null,
                'card_number'     => sprintf('PD-%s-%05d', now()->year, $membership->id),
                'holder'          => [
                    'first_name' => $request->user()->first_name,
                    'last_name'  => $request->user()->last_name,
                ],
                'venue'           => [
                    'name'     => $tenant->display_name,
                    'logo_url' => $tenant->logo_url,
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------

    private function resolveStatus(TenantMembership $membership): string
    {
        if (! $membership->is_active) {
            return 'none';
        }

        if ($membership->membership_ends_at && $membership->membership_ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function venueData(TenantMembership $membership): array
    {
        $tenant = $membership->tenant;
        $status = $this->resolveStatus($membership);

        return [
            'slug'              => $tenant?->slug,
            'name'              => $tenant?->display_name,
            'logo_url'          => $tenant?->logo_url,
            'city'              => $tenant?->city,
            'is_active'         => $membership->is_active,
            'membership_type'   => $membership->membership_type,
            'membership_status' => $status,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Memberships\FamilyMembershipService;
use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Familieabonnement beheren vanuit de frontdesk (ledenmodule).
 */
class FamilyController extends Controller
{
    public function __construct(
        protected FamilyMembershipService $families,
    ) {
    }

    /**
     * Gezin ophalen. Werkt zowel met het id van het hoofd als van een gezinslid.
     */
    public function show(CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        $head = $this->resolveHead($currentTenant, $tenantMembership);

        return response()->json(['data' => $this->mapFamily($head)]);
    }

    public function storeMember(Request $request, CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        $head = $this->resolveHead($currentTenant, $tenantMembership);

        $data = $request->validate([
            'first_name'      => ['required', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'birth_date'      => ['nullable', 'date'],
            'membership_type' => ['required', 'in:adult,student'],
            'email'           => ['nullable', 'email', 'max:255'],
            'rfid_uid'        => ['nullable', 'string', 'max:100'],
            'force'           => ['sometimes', 'boolean'],
        ]);

        try {
            $member = $this->families->addMember($head, $data, (bool) ($data['force'] ?? false));
        } catch (ValidationException $e) {
            $errors = $e->errors();

            if (isset($errors[FamilyMembershipService::ERROR_LIMIT])) {
                return response()->json([
                    'message'       => $errors[FamilyMembershipService::ERROR_LIMIT][0],
                    'limit_reached' => true,
                ], 422);
            }

            throw $e;
        }

        if (! empty($member->rfid_uid)) {
            app(MemberController::class)->syncCard($member, $currentTenant, $request->attributes->get('frontdesk_user')?->id);
        }

        return response()->json([
            'message' => 'Gezinslid toegevoegd.',
            'data'    => $this->mapFamily($head->fresh()),
        ], 201);
    }

    public function destroyMember(CurrentTenant $currentTenant, TenantMembership $tenantMembership, TenantMembership $member): JsonResponse
    {
        $head = $this->resolveHead($currentTenant, $tenantMembership);
        abort_unless((int) $member->tenant_id === (int) $currentTenant->id(), 404);

        $this->families->detachMember($head, $member);

        return response()->json([
            'message' => 'Gezinslid uit het gezin gehaald.',
            'data'    => $this->mapFamily($head->fresh()),
        ]);
    }

    // ------------------------------------------------------------------

    private function resolveHead(CurrentTenant $currentTenant, TenantMembership $membership): TenantMembership
    {
        abort_unless((int) $membership->tenant_id === (int) $currentTenant->id(), 404);

        $head = $membership->isFamilyHead() ? $membership : $membership->family;

        if (! $head || ! $head->isFamilyHead()) {
            abort(response()->json(['message' => 'Dit lid heeft geen familieabonnement.'], 422));
        }

        return $head;
    }

    private function mapFamily(TenantMembership $head): array
    {
        $head->load(['account', 'familyMembers.account']);

        $members = $head->familyMembers
            ->sortBy(fn (TenantMembership $m) => [$m->membership_type === 'adult' ? 0 : 1, $m->account?->birth_date?->toDateString() ?? '9999'])
            ->values();

        return [
            'head_membership_id' => $head->id,
            'name'               => $this->families->familyName($head),
            'membership_starts_at' => $head->membership_starts_at?->toDateString(),
            'membership_ends_at'   => $head->membership_ends_at?->toDateString(),
            'is_active'          => (bool) $head->is_active,
            'counts'             => $this->families->counts($head),
            'limits'             => $this->families->limits((int) $head->tenant_id),
            'head'               => $this->mapPerson($head, true),
            'members'            => $members->map(fn (TenantMembership $m) => $this->mapPerson($m, false))->all(),
        ];
    }

    private function mapPerson(TenantMembership $m, bool $isHead): array
    {
        $account = $m->account;
        $email   = $account?->email;

        return [
            'tenant_membership_id' => $m->id,
            'account_id'           => $m->account_id,
            'first_name'           => $account?->first_name,
            'last_name'            => $account?->last_name,
            'full_name'            => trim(($account?->first_name ?? '') . ' ' . ($account?->last_name ?? '')),
            'email'                => $email && ! self::isPlaceholderEmail($email) ? $email : null,
            'birth_date'           => $account?->birth_date?->toDateString(),
            'membership_type'      => $isHead ? 'adult' : $m->membership_type,
            'rfid_uid'             => $m->rfid_uid,
            'is_head'              => $isHead,
            'is_managed'           => (bool) $account?->managed_by_account_id,
        ];
    }

    public static function isPlaceholderEmail(?string $email): bool
    {
        return ! $email
            || str_contains($email, '@migrated.local')
            || str_contains($email, '@playdrive.local');
    }
}

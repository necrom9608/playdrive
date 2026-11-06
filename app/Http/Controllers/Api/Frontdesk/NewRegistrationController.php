<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Domain\Memberships\MemberCardService;
use App\Http\Controllers\Controller;
use App\Mail\MemberLifecycleMail;
use App\Models\TenantMembership;
use App\Services\MailLogger;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NewRegistrationController extends Controller
{
    /**
     * Nieuwe leden van deze tenant (via QR / "lid worden" of de app) die nog geen abonnement
     * hebben en nog niet als "gezien" gemarkeerd zijn. Laatste 7 dagen, zodat de lijst kort blijft.
     * Kinderen (beheerde accounts) staan niet apart in de lijst maar onder hun ouder.
     */
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $tenantId = (int) $currentTenant->id();

        $registrations = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('legacy_member_id')
            ->whereNull('frontdesk_seen_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhereNull('membership_starts_at');
            })
            ->whereHas('account', fn ($q) => $q->whereNull('managed_by_account_id'))
            ->with('account:id,first_name,last_name,email,email_verified_at,created_at,managed_by_account_id')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $children = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('account', fn ($q) => $q->whereIn('managed_by_account_id', $registrations->pluck('account_id')))
            ->with('account:id,managed_by_account_id,first_name,birth_date')
            ->get()
            ->groupBy(fn (TenantMembership $m) => $m->account?->managed_by_account_id);

        $data = $registrations->map(function (TenantMembership $tm) use ($children) {
            $account = $tm->account;
            $created = $tm->created_at;

            return [
                'membership_id'    => $tm->id,
                'account_id'       => $tm->account_id,
                'code'             => 'PDL-' . $tm->id,
                'first_name'       => $account?->first_name,
                'last_name'        => $account?->last_name,
                'full_name'        => trim(($account?->first_name ?? '') . ' ' . ($account?->last_name ?? '')),
                'email'            => $account?->email,
                'email_verified'   => (bool) $account?->email_verified_at,
                // Account bestond al (bv. lid van een andere venue) en werd nu gekoppeld
                'existing_account' => $account?->created_at && $created && $account->created_at->lt($created->copy()->subMinutes(5)),
                'children'         => ($children->get($tm->account_id) ?? collect())->map(fn (TenantMembership $c) => [
                    'membership_id' => $c->id,
                    'first_name'    => $c->account?->first_name,
                    'age'           => $c->account?->birth_date?->age,
                ])->values(),
                'registered_at'    => $created?->toIso8601String(),
                'registered_label' => $created?->diffForHumans(),
                'is_new'           => $created && $created->diffInHours(now()) < 24,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    /** "Gezien": weg uit het paneel Nieuwe leden. */
    public function seen(CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        abort_unless((int) $tenantMembership->tenant_id === (int) $currentTenant->id(), 404);

        $tenantMembership->forceFill(['frontdesk_seen_at' => now()])->save();

        return response()->json(['message' => 'Gemarkeerd als gezien.']);
    }

    /**
     * Activeer een abonnement en stuur een bevestigingsmail.
     */
    public function activate(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $data = $request->validate([
            'membership_id'        => ['required', 'integer', 'exists:tenant_memberships,id'],
            'membership_type'      => ['nullable', 'in:adult,student'],
            'membership_starts_at' => ['nullable', 'date'],
            'membership_ends_at'   => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
        ]);

        $membership = TenantMembership::query()
            ->where('id', $data['membership_id'])
            ->where('tenant_id', $currentTenant->id())
            ->with('account')
            ->firstOrFail();

        $today     = now()->startOfDay();
        $startDate = ! empty($data['membership_starts_at'])
            ? Carbon::parse($data['membership_starts_at'])->startOfDay()
            : $today;
        $endsDate  = ! empty($data['membership_ends_at'])
            ? Carbon::parse($data['membership_ends_at'])->startOfDay()
            : $startDate->copy()->addYear();

        DB::transaction(function () use ($membership, $data, $startDate, $endsDate) {
            $membership->update([
                'is_active'                 => true,
                'membership_type'           => $data['membership_type'] ?? 'adult',
                'membership_starts_at'      => $startDate->toDateString(),
                'membership_ends_at'        => $endsDate->toDateString(),
                'confirmation_mail_sent_at' => now(),
                'frontdesk_seen_at'         => $membership->frontdesk_seen_at ?? now(),
            ]);
        });

        // Kaart klaarzetten voor Backoffice → Kaarten (nog te printen)
        app(MemberCardService::class)->ensureCard($membership->fresh(), $request->attributes->get('frontdesk_user')?->id);

        // Bevestigingsmail sturen als er een geldig e-mailadres is
        $account = $membership->account;

        if ($account && $this->hasValidEmail($account->email)) {
            $mailable = new MemberLifecycleMail($membership, 'confirmation');

            Mail::to($account->email, $account->full_name)->send($mailable);

            MailLogger::log(
                toEmail:   $account->email,
                toName:    $account->full_name,
                subject:   'Bevestiging van je abonnement',
                tenantId:  (int) $currentTenant->id(),
                accountId: $account->id,
                mailType:  'member_lifecycle_confirmation',
                htmlBody:  $mailable->render(),
            );
        }

        return response()->json(['message' => 'Abonnement geactiveerd.']);
    }

    private function hasValidEmail(?string $email): bool
    {
        if (! $email) return false;

        return ! str_contains($email, '@migrated.local')
            && ! str_contains($email, '@playdrive.local');
    }
}

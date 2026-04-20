<?php

namespace App\Http\Controllers\Api\Frontdesk;

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
     * Accounts die zich via QR code geregistreerd hebben bij deze tenant
     * maar nog geen actief lidmaatschap hebben.
     * Beperkt tot de laatste 7 dagen zodat de lijst beheersbaar blijft.
     */
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        $registrations = TenantMembership::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereNull('legacy_member_id')
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhereNull('membership_starts_at');
            })
            ->with('account:id,first_name,last_name,email,created_at')
            ->whereHas('account', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->orderByDesc('id')
            ->get()
            ->map(fn (TenantMembership $tm) => [
                'membership_id'    => $tm->id,
                'account_id'       => $tm->account_id,
                'first_name'       => $tm->account?->first_name,
                'last_name'        => $tm->account?->last_name,
                'full_name'        => trim(($tm->account?->first_name ?? '') . ' ' . ($tm->account?->last_name ?? '')),
                'email'            => $tm->account?->email,
                'registered_at'    => $tm->account?->created_at?->toIso8601String(),
                'registered_label' => $tm->account?->created_at?->diffForHumans(),
                'is_new'           => $tm->account?->created_at && $tm->account->created_at->diffInHours(now()) < 24,
            ])
            ->values();

        return response()->json(['data' => $registrations]);
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
            ]);
        });

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

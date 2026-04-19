<?php

namespace App\Http\Controllers\Api\Frontdesk;

use App\Http\Controllers\Controller;
use App\Mail\MemberInviteMail;
use App\Models\MailLog;
use App\Models\TenantMembership;
use App\Services\MailLogger;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class MemberInviteController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant, TenantMembership $tenantMembership): JsonResponse
    {
        abort_unless((int) $tenantMembership->tenant_id === (int) $currentTenant->id(), 404);

        $account = $tenantMembership->account;

        if (! $account) {
            return response()->json(['message' => 'Geen account gekoppeld aan dit lid.'], 422);
        }

        if (str_contains($account->email, '@migrated.local') || str_contains($account->email, '@playdrive.local')) {
            return response()->json(['message' => 'Dit lid heeft geen geldig e-mailadres.'], 422);
        }

        // Controleer of er al recent een uitnodiging verstuurd werd (laatste 24u)
        $recentInvite = MailLog::query()
            ->where('account_id', $account->id)
            ->where('mail_type', 'member_invite')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($recentInvite) {
            return response()->json([
                'message' => 'Er werd al een uitnodiging verstuurd in de afgelopen 24 uur.',
            ], 422);
        }

        $token    = Password::broker('accounts')->createToken($account);
        $resetUrl = url('/member#/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $account->email,
        ]));

        $tenantName = $tenantMembership->tenant?->display_name;
        $mailable   = new MemberInviteMail($account, $resetUrl, $tenantName);

        Mail::to($account->email, $account->full_name)->send($mailable);

        MailLogger::log(
            toEmail:   $account->email,
            subject:   'Activeer je PlayDrive account',
            toName:    $account->full_name,
            tenantId:  (int) $currentTenant->id(),
            accountId: $account->id,
            mailType:  'member_invite',
            htmlBody:  $mailable->render(),
        );

        return response()->json([
            'message' => "Uitnodiging verstuurd naar {$account->email}.",
        ]);
    }
}

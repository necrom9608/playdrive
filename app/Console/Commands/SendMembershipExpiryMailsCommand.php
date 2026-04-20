<?php

namespace App\Console\Commands;

use App\Mail\MemberLifecycleMail;
use App\Models\TenantMembership;
use App\Services\MailLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMembershipExpiryMailsCommand extends Command
{
    protected $signature   = 'memberships:send-expiry-mails {--dry-run : Toon wat er verstuurd zou worden zonder effectief te verzenden}';
    protected $description = 'Verstuurt automatisch vervalmails: 14 dagen voor vervaldatum, 7 dagen voor vervaldatum, en na vervaldatum.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today  = now()->startOfDay();

        $sent14d   = 0;
        $sent7d    = 0;
        $sentExp   = 0;
        $skipped   = 0;

        // ------------------------------------------------------------------
        // 14-dagenherinnering
        // Leden waarbij membership_ends_at tussen vandaag en 14 dagen ligt
        // en nog geen 14d-mail ontvangen hebben.
        // ------------------------------------------------------------------
        $window14dStart = $today->copy()->addDays(13)->startOfDay();
        $window14dEnd   = $today->copy()->addDays(14)->endOfDay();

        $memberships14d = TenantMembership::query()
            ->where('is_active', true)
            ->whereBetween('membership_ends_at', [$window14dStart, $window14dEnd])
            ->whereNull('expiry_warning_14d_mail_sent_at')
            ->with('account', 'tenant')
            ->get();

        foreach ($memberships14d as $membership) {
            if (! $this->hasValidEmail($membership->account?->email)) {
                $skipped++;
                continue;
            }

            $this->line("14d: {$membership->full_name} <{$membership->account->email}> — vervalt {$membership->membership_ends_at->format('d/m/Y')}");

            if (! $dryRun) {
                $this->sendMail($membership, 'expiring_14d', 'member_lifecycle_expiring_14d', 'Je abonnement vervalt over 2 weken');
                $membership->update(['expiry_warning_14d_mail_sent_at' => now()]);
            }

            $sent14d++;
        }

        // ------------------------------------------------------------------
        // 7-dagenherinnering
        // ------------------------------------------------------------------
        $window7dStart = $today->copy()->addDays(6)->startOfDay();
        $window7dEnd   = $today->copy()->addDays(7)->endOfDay();

        $memberships7d = TenantMembership::query()
            ->where('is_active', true)
            ->whereBetween('membership_ends_at', [$window7dStart, $window7dEnd])
            ->whereNull('expiry_warning_7d_mail_sent_at')
            ->with('account', 'tenant')
            ->get();

        foreach ($memberships7d as $membership) {
            if (! $this->hasValidEmail($membership->account?->email)) {
                $skipped++;
                continue;
            }

            $this->line("7d:  {$membership->full_name} <{$membership->account->email}> — vervalt {$membership->membership_ends_at->format('d/m/Y')}");

            if (! $dryRun) {
                $this->sendMail($membership, 'expiring_7d', 'member_lifecycle_expiring_7d', 'Je abonnement vervalt over 1 week');
                $membership->update(['expiry_warning_7d_mail_sent_at' => now()]);
            }

            $sent7d++;
        }

        // ------------------------------------------------------------------
        // Verlopen mail
        // Leden waarvan het abonnement gisteren of eerder vervallen is
        // en nog geen expired-mail ontvangen hebben.
        // ------------------------------------------------------------------
        $expiredBefore = $today->copy()->subDay()->endOfDay();

        $membershipsExpired = TenantMembership::query()
            ->where('is_active', true)
            ->where('membership_ends_at', '<=', $expiredBefore)
            ->whereNull('expired_mail_sent_at')
            ->with('account', 'tenant')
            ->get();

        foreach ($membershipsExpired as $membership) {
            if (! $this->hasValidEmail($membership->account?->email)) {
                $skipped++;
                continue;
            }

            $this->line("EXP: {$membership->full_name} <{$membership->account->email}> — vervallen op {$membership->membership_ends_at->format('d/m/Y')}");

            if (! $dryRun) {
                $this->sendMail($membership, 'expired', 'member_lifecycle_expired', 'Je abonnement is verlopen');
                $membership->update(['expired_mail_sent_at' => now()]);
            }

            $sentExp++;
        }

        // ------------------------------------------------------------------
        // Samenvatting
        // ------------------------------------------------------------------
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $this->info("{$prefix}14-dagenmails: {$sent14d}");
        $this->info("{$prefix}7-dagenmails:  {$sent7d}");
        $this->info("{$prefix}Vervallen:     {$sentExp}");

        if ($skipped > 0) {
            $this->warn("{$prefix}Overgeslagen (ongeldig e-mail): {$skipped}");
        }

        return self::SUCCESS;
    }

    private function sendMail(TenantMembership $membership, string $type, string $mailType, string $subject): void
    {
        $account  = $membership->account;
        $mailable = new MemberLifecycleMail($membership, $type);

        Mail::to($account->email, $account->full_name)->send($mailable);

        MailLogger::log(
            toEmail:   $account->email,
            toName:    $account->full_name,
            subject:   $subject,
            tenantId:  (int) $membership->tenant_id,
            accountId: $account->id,
            mailType:  $mailType,
            htmlBody:  $mailable->render(),
        );
    }

    private function hasValidEmail(?string $email): bool
    {
        if (! $email) return false;

        return ! str_contains($email, '@migrated.local')
            && ! str_contains($email, '@playdrive.local');
    }
}

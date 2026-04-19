<?php

namespace App\Console\Commands;

use App\Mail\MemberInviteMail;
use App\Models\Account;
use App\Models\MailLog;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\MailLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class InviteLegacyMembersCommand extends Command
{
    protected $signature = 'members:invite
                            {--tenant-id= : Enkel leden van deze tenant uitnodigen}
                            {--dry-run    : Toon wat er verstuurd zou worden zonder effectief te versturen}
                            {--limit=50   : Maximaal aantal uitnodigingen per run}
                            {--force      : Ook accounts uitnodigen die al een wachtwoord hebben}';

    protected $description = 'Stuur uitnodigingsmails naar legacy-leden die nog geen wachtwoord hebben ingesteld.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
        $dryRun   = (bool) $this->option('dry-run');
        $limit    = (int) $this->option('limit');
        $force    = (bool) $this->option('force');

        $this->info($dryRun ? '🔍 DRY RUN — er worden geen mails verstuurd.' : '📨 Uitnodigingen versturen...');
        $this->newLine();

        // Zoek accounts met echte e-mail, geen wachtwoord (tenzij --force)
        $query = Account::query()
            ->whereNotLike('email', '%@migrated.local')
            ->whereNotLike('email', '%@playdrive.local');

        if (! $force) {
            $query->whereNull('password');
        }

        // Filter op tenant indien opgegeven
        if ($tenantId) {
            $query->whereHas('memberships', fn ($q) => $q->where('tenant_id', $tenantId));
        } else {
            // Enkel accounts die aan minstens één tenant gekoppeld zijn
            $query->whereHas('memberships');
        }

        // Sla accounts over die al een uitnodiging ontvangen hebben
        if (! $force) {
            $alreadyInvited = MailLog::query()
                ->where('mail_type', 'member_invite')
                ->pluck('to_email')
                ->map(fn ($e) => strtolower($e))
                ->all();

            if (! empty($alreadyInvited)) {
                $query->whereNotIn('email', $alreadyInvited);
            }
        }

        $accounts = $query->limit($limit)->get();

        if ($accounts->isEmpty()) {
            $this->info('Geen accounts gevonden om uit te nodigen.');
            return self::SUCCESS;
        }

        $this->info("Gevonden: {$accounts->count()} account(s)");
        $this->newLine();

        // Tenant naam ophalen voor de mail
        $tenantName = null;
        if ($tenantId) {
            $tenantName = Tenant::find($tenantId)?->display_name;
        }

        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        $rows = [];

        foreach ($accounts as $account) {
            $memberships = TenantMembership::query()
                ->where('account_id', $account->id)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->get();

            $membershipInfo = $memberships->map(fn ($m) => "tenant #{$m->tenant_id}")->implode(', ');

            if ($dryRun) {
                $rows[] = [
                    $account->id,
                    $account->email,
                    "{$account->first_name} {$account->last_name}",
                    $membershipInfo,
                ];
                $sent++;
                continue;
            }

            try {
                // Genereer password reset token via de accounts broker
                $token = Password::broker('accounts')->createToken($account);

                // Bouw de reset URL naar de member app
                $resetUrl = url('/member#/reset-password?' . http_build_query([
                    'token' => $token,
                    'email' => $account->email,
                ]));

                $mailable = new MemberInviteMail($account, $resetUrl, $tenantName);

                Mail::to($account->email, "{$account->first_name} {$account->last_name}")
                    ->send($mailable);

                // Log de mail
                MailLogger::log(
                    toEmail:   $account->email,
                    subject:   'Activeer je PlayDrive account',
                    toName:    "{$account->first_name} {$account->last_name}",
                    tenantId:  $tenantId,
                    accountId: $account->id,
                    mailType:  'member_invite',
                    htmlBody:  $mailable->render(),
                );

                $rows[] = [$account->id, $account->email, "{$account->first_name} {$account->last_name}", '✓ Verstuurd'];
                $sent++;

            } catch (\Throwable $e) {
                $rows[] = [$account->id, $account->email, "{$account->first_name} {$account->last_name}", '✗ ' . $e->getMessage()];
                $failed++;

                $this->error("Mislukt voor {$account->email}: " . $e->getMessage());
            }
        }

        $this->table(
            $dryRun
                ? ['ID', 'E-mail', 'Naam', 'Memberships']
                : ['ID', 'E-mail', 'Naam', 'Status'],
            $rows
        );

        $this->newLine();

        if ($dryRun) {
            $this->info("Zou {$sent} uitnodiging(en) versturen. Gebruik zonder --dry-run om effectief te versturen.");
        } else {
            $this->info("✓ Verstuurd: {$sent}");
            if ($failed > 0) {
                $this->warn("✗ Mislukt:   {$failed}");
            }
        }

        return self::SUCCESS;
    }
}

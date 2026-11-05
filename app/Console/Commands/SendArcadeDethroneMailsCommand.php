<?php

namespace App\Console\Commands;

use App\Domain\Arcade\ArcadeGames;
use App\Mail\ArcadeDethroneMail;
use App\Models\Account;
use App\Models\ArcadeDethrone;
use App\Models\ArcadeSong;
use App\Models\MailLog;
use App\Models\Tenant;
use App\Services\MailLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Verstuurt gebundelde "je #1 is overgenomen"-mails.
 *  - een overname wacht 30 min: pakt de speler zijn #1 meteen terug, dan geen mail
 *  - max. één mail per account per 20 uur; wat later binnenkomt gaat mee in de volgende
 *  - mail uitgeschakeld of geen geldig adres → gemarkeerd als afgehandeld, geen mail
 */
class SendArcadeDethroneMailsCommand extends Command
{
    protected $signature = 'arcade:notify-dethrones {--dry-run : Toon wat er verstuurd zou worden zonder te verzenden}';

    protected $description = 'Mailt spelers wiens #1 op een chart werd overgenomen (gebundeld, max. 1 per dag).';

    public const MAIL_TYPE = 'arcade_dethrone';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // teruggepakt voor er gemaild werd: gewoon afsluiten
        if (! $dry) {
            ArcadeDethrone::query()->whereNull('notified_at')->whereNotNull('reclaimed_at')->update(['notified_at' => now()]);
        }

        $pending = ArcadeDethrone::query()
            ->whereNull('notified_at')
            ->whereNull('reclaimed_at')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['account', 'tenant', 'byPlayer'])
            ->orderBy('created_at')
            ->get();

        $sent = 0;
        $skipped = 0;
        $waiting = 0;

        foreach ($pending->groupBy(fn ($d) => $d->lost_account_id . '|' . $d->tenant_id) as $group) {
            /** @var ArcadeDethrone $first */
            $first = $group->first();
            $account = $first->account;
            $tenant = $first->tenant;

            if (! $account || ! $tenant || ! $account->arcade_notify_mail || ! $this->validEmail($account->email)) {
                $skipped += $group->count();
                $dry || $this->markNotified($group);
                continue;
            }

            $recent = MailLog::query()
                ->where('account_id', $account->id)
                ->where('mail_type', self::MAIL_TYPE)
                ->where('created_at', '>=', now()->subHours(20))
                ->exists();
            if ($recent) {
                $waiting += $group->count();
                continue;
            }

            $items = $this->items($group);
            $this->line(sprintf('%s <%s> · %s · %d chart(s)', $account->full_name, $account->email, $tenant->display_name, count($items)));

            if ($dry) {
                $sent++;
                continue;
            }

            try {
                $mail = new ArcadeDethroneMail(
                    $account,
                    (string) $tenant->display_name,
                    $items,
                    url('/member#/mijn/stats'),
                    URL::signedRoute('arcade.mail.off', ['account' => $account->id]),
                );
                Mail::to($account->email, $account->full_name)->send($mail);

                MailLogger::log(
                    toEmail:   $account->email,
                    subject:   $mail->subjectLine(),
                    toName:    $account->full_name,
                    tenantId:  (int) $tenant->id,
                    accountId: $account->id,
                    mailType:  self::MAIL_TYPE,
                    htmlBody:  $mail->render(),
                );

                $this->markNotified($group);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Arcade-mail (overgenomen #1) mislukt', ['account_id' => $account->id, 'error' => $e->getMessage()]);
                $this->error("Mislukt voor {$account->email}: {$e->getMessage()}");
            }
        }

        $this->info("Verstuurd: {$sent} · overgeslagen: {$skipped} · wacht op volgende ronde: {$waiting}" . ($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /** Eén regel per chart (de laatste overname telt). */
    private function items(Collection $group): array
    {
        $songs = ArcadeSong::query()
            ->where(function ($q) use ($group) {
                foreach ($group->unique(fn ($d) => "{$d->game}|{$d->song_id}") as $d) {
                    $q->orWhere(fn ($w) => $w->where('game', $d->game)->where('song_id', $d->song_id));
                }
            })
            ->get(['game', 'song_id', 'title', 'levels'])
            ->keyBy(fn ($s) => "{$s->game}|{$s->song_id}");

        return $group->sortByDesc('created_at')
            ->unique(fn ($d) => "{$d->game}|{$d->song_id}|{$d->chart}")
            ->map(function (ArcadeDethrone $d) use ($songs) {
                $song = $songs["{$d->game}|{$d->song_id}"] ?? null;
                $level = $song?->levels[$d->chart] ?? null;

                return [
                    'title'      => $song?->title ?: "Song #{$d->song_id}",
                    'chart'      => strtoupper($d->chart) . ($level ? " · lv {$level}" : ''),
                    'game'       => ArcadeGames::short($d->game),
                    'by'         => $d->byPlayer?->displayName($d->game) ?? 'Een speler',
                    'by_score'   => $d->by_score,
                    'your_score' => $d->lost_score,
                ];
            })
            ->values()
            ->all();
    }

    private function markNotified(Collection $group): void
    {
        ArcadeDethrone::query()->whereIn('id', $group->pluck('id'))->update(['notified_at' => now()]);
    }

    private function validEmail(?string $email): bool
    {
        return $email
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! str_ends_with($email, '@migrated.local')
            && ! str_ends_with($email, '@playdrive.local');
    }
}

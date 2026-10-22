<?php

namespace App\Domain\MailAssistant;

use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Haalt nieuwe mails op uit de mailbox van een tenant, bewaart ze en laat er een concept voor opstellen.
 * Mails worden niet als gelezen gemarkeerd (BODY.PEEK).
 */
class MailboxFetcher
{
    private const MAX_PER_RUN = 25;
    private const MAX_RAW_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly ReplyDrafter $drafter,
    ) {
    }

    public static function client(MailAssistantSetting $settings): ImapClient
    {
        if (! $settings->isMailboxConfigured()) {
            throw new RuntimeException('Mailbox is nog niet ingesteld.');
        }

        $client = new ImapClient($settings->imap_host, (int) $settings->imap_port, $settings->imap_encryption ?: 'ssl');
        $client->connect();
        $client->login($settings->imap_username, $settings->imap_password);

        return $client;
    }

    /**
     * @return array{fetched: int, drafted: int, ignored: int, errors: int}
     */
    public function run(Tenant $tenant, MailAssistantSetting $settings, bool $draft = true): array
    {
        $stats = ['fetched' => 0, 'drafted' => 0, 'ignored' => 0, 'errors' => 0];

        try {
            $client = self::client($settings);
            $client->select($settings->imap_folder ?: 'INBOX');

            $uidValidity = $client->uidValidity();
            $fresh = $settings->last_uid === null || $settings->uid_validity !== $uidValidity;

            $uids = $fresh
                ? $client->uidsSince(CarbonImmutable::now()->subDays(max(0, (int) $settings->fetch_since_days)))
                : $client->uidsAfter((int) $settings->last_uid);

            $uids = array_slice($uids, 0, self::MAX_PER_RUN);
            $lastUid = $fresh ? 0 : (int) $settings->last_uid;

            foreach ($uids as $uid) {
                $lastUid = max($lastUid, $uid);

                if (MailAssistantMessage::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('uid_validity', $uidValidity)
                    ->where('uid', $uid)
                    ->exists()) {
                    continue;
                }

                $fetched = $client->fetchMessage($uid);
                if (! $fetched || $fetched['raw'] === '') {
                    continue;
                }

                $message = $this->store($tenant, $settings, $uidValidity, $fetched);
                if (! $message) {
                    continue;
                }

                $stats['fetched']++;

                if ($message->status === MailAssistantMessage::STATUS_IGNORED) {
                    $stats['ignored']++;
                    continue;
                }

                if ($draft) {
                    $this->draftSafely($tenant, $settings, $message) ? $stats['drafted']++ : $stats['errors']++;
                }
            }

            // Bij een eerste run zonder mails: verder tellen vanaf de huidige UIDNEXT
            if ($fresh && $uids === [] && $client->uidNext()) {
                $lastUid = $client->uidNext() - 1;
            }

            $client->logout();

            $settings->forceFill([
                'uid_validity' => $uidValidity,
                'last_uid' => $lastUid,
                'last_checked_at' => now(),
                'last_error' => null,
                'last_error_at' => null,
            ])->save();
        } catch (Throwable $e) {
            $settings->forceFill([
                'last_checked_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'last_error_at' => now(),
            ])->save();

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        return $stats;
    }

    public function draftSafely(Tenant $tenant, MailAssistantSetting $settings, MailAssistantMessage $message): bool
    {
        try {
            $this->drafter->draft($tenant, $settings, $message);

            return true;
        } catch (Throwable $e) {
            Log::warning('Mailassistent: concept opstellen mislukt', [
                'tenant_id' => $tenant->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->forceFill([
                'status' => MailAssistantMessage::STATUS_ERROR,
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    private function store(Tenant $tenant, MailAssistantSetting $settings, ?int $uidValidity, array $fetched): ?MailAssistantMessage
    {
        $raw = strlen($fetched['raw']) > self::MAX_RAW_BYTES ? substr($fetched['raw'], 0, self::MAX_RAW_BYTES) : $fetched['raw'];
        $mime = MimeMessage::parse($raw);

        $from = $mime->from();
        if (! $from['email']) {
            return null;
        }

        $replyTo = $mime->replyTo()['email'];
        $ownAddress = $settings->senderAddress();

        $ignoreReason = $mime->automatedReason();
        if (! $ignoreReason && $ownAddress && $from['email'] === $ownAddress) {
            $ignoreReason = 'Verstuurd vanaf het eigen adres';
        }

        $receivedAt = $mime->date();
        if (! $receivedAt && $fetched['internal_date']) {
            try {
                $receivedAt = new \DateTimeImmutable($fetched['internal_date']);
            } catch (Throwable) {
                $receivedAt = null;
            }
        }

        $references = trim(implode(' ', array_filter([$mime->rawHeader('references'), $mime->rawHeader('in-reply-to')])));

        return MailAssistantMessage::query()->create([
            'tenant_id' => $tenant->id,
            'uid' => $fetched['uid'],
            'uid_validity' => $uidValidity,
            'message_id' => $mime->messageId() ? mb_substr($mime->messageId(), 0, 500) : null,
            'in_reply_to' => $mime->rawHeader('in-reply-to') ? mb_substr($mime->rawHeader('in-reply-to'), 0, 500) : null,
            'references' => $references !== '' ? mb_substr($references, 0, 5000) : null,
            'from_email' => mb_substr($from['email'], 0, 255),
            'from_name' => $from['name'] ? mb_substr($from['name'], 0, 255) : null,
            'reply_to' => $replyTo && $replyTo !== $from['email'] ? mb_substr($replyTo, 0, 255) : null,
            'subject' => mb_substr($mime->subject(), 0, 500),
            'body_text' => mb_substr($mime->text, 0, 100000),
            'received_at' => $receivedAt ? CarbonImmutable::instance($receivedAt) : now(),
            'status' => $ignoreReason ? MailAssistantMessage::STATUS_IGNORED : MailAssistantMessage::STATUS_NEW,
            'ignore_reason' => $ignoreReason,
        ]);
    }
}

<?php

namespace App\Domain\MailAssistant;

use App\Models\MailAssistantMessage;
use App\Models\MailAssistantSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Verstuurt een goedgekeurd antwoord via de SMTP-server van de tenant, in dezelfde mailthread,
 * en zet een kopie in "Verzonden" van de mailbox.
 */
class ReplySender
{
    public static function transport(MailAssistantSetting $settings): EsmtpTransport
    {
        if (! $settings->isSmtpConfigured()) {
            throw new RuntimeException('SMTP (versturen) is nog niet ingesteld.');
        }

        $port = (int) ($settings->smtp_port ?: 587);
        $transport = new EsmtpTransport($settings->smtp_host, $port, $port === 465 ? true : null);
        $transport->setUsername($settings->smtp_username ?: $settings->imap_username);
        $transport->setPassword($settings->smtp_password ?: $settings->imap_password);

        return $transport;
    }

    public function send(Tenant $tenant, MailAssistantSetting $settings, MailAssistantMessage $message, string $body, ?int $userId): MailAssistantMessage
    {
        $fromAddress = $settings->senderAddress();
        if (! $fromAddress) {
            throw new RuntimeException('Geen afzenderadres ingesteld.');
        }

        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            throw new RuntimeException('Het antwoord is leeg.');
        }

        $subject = trim((string) $message->subject);
        if (! preg_match('/^(re|antw|aw|sv)\s*:/i', $subject)) {
            $subject = 'Re: ' . ($subject !== '' ? $subject : '(geen onderwerp)');
        }

        $email = (new Email())
            ->from(new Address($fromAddress, $settings->from_name ?: $tenant->display_name))
            ->to(new Address($message->replyAddress(), (string) $message->from_name))
            ->subject($subject)
            ->text($body . "\n\n" . $this->quoteOriginal($message));

        $originalId = $this->bareId($message->message_id);
        if ($originalId) {
            $references = array_values(array_unique(array_filter(array_merge(
                $this->idList($message->references),
                [$originalId]
            ))));

            try {
                $email->getHeaders()->addIdHeader('In-Reply-To', $originalId);
                $email->getHeaders()->addIdHeader('References', array_slice($references, -20));
            } catch (Throwable) {
                // ongeldig Message-ID van de afzender: dan maar zonder thread-headers
                $email->getHeaders()->remove('In-Reply-To');
                $email->getHeaders()->remove('References');
            }
        }

        $sent = self::transport($settings)->send($email);
        if (! $sent) {
            throw new RuntimeException('Versturen mislukt.');
        }

        $message->forceFill([
            'status' => MailAssistantMessage::STATUS_SENT,
            'sent_body' => $body,
            'sent_edited' => $this->normalize($body) !== $this->normalize((string) $message->draft_original),
            'sent_at' => now(),
            'sent_by' => $userId,
            'handled_by' => $userId,
            'handled_at' => now(),
        ])->save();

        $this->storeInMailbox($settings, $message, $sent->toString());

        return $message;
    }

    /** Kopie in "Verzonden" + origineel als beantwoord markeren. Fouten hier blokkeren het versturen niet. */
    private function storeInMailbox(MailAssistantSetting $settings, MailAssistantMessage $message, string $raw): void
    {
        try {
            $client = MailboxFetcher::client($settings);

            $sentFolder = $settings->sent_folder ?: $client->findSentFolder();
            if ($sentFolder) {
                $client->append($sentFolder, $raw, ['\\Seen']);
            }

            if ($message->uid && $message->uid_validity) {
                $client->select($settings->imap_folder ?: 'INBOX');
                if ($client->uidValidity() === (int) $message->uid_validity) {
                    $client->addFlags((int) $message->uid, ['\\Answered']);
                }
            }

            $client->logout();
        } catch (Throwable $e) {
            Log::warning('Mailassistent: kopie in Verzonden mislukt', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function quoteOriginal(MailAssistantMessage $message): string
    {
        $date = $message->received_at?->timezone('Europe/Brussels')->format('d/m/Y H:i') ?? '';
        $who = $message->from_name ? "{$message->from_name} <{$message->from_email}>" : $message->from_email;
        $original = MimeMessage::stripQuoted((string) $message->body_text);
        $original = mb_substr($original, 0, 6000);

        $quoted = implode("\n", array_map(fn ($line) => '> ' . $line, explode("\n", $original)));

        return "Op {$date} schreef {$who}:\n{$quoted}";
    }

    private function bareId(?string $id): ?string
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        return trim($id, '<> ') ?: null;
    }

    private function idList(?string $value): array
    {
        preg_match_all('/<([^<>\s]+)>/', (string) $value, $m);

        return $m[1] ?? [];
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}

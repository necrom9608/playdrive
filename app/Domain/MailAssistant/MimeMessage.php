<?php

namespace App\Domain\MailAssistant;

/**
 * Eenvoudige MIME-parser: headers, afzender, onderwerp en de leesbare tekst van een mail.
 * Bijlagen worden genegeerd.
 */
class MimeMessage
{
    /** @var array<string, string[]> headernaam (lowercase) => waarden */
    public array $headers = [];

    public string $text = '';

    public static function parse(string $raw): self
    {
        $message = new self();
        [$headerBlock, $body] = self::splitHeaderBody($raw);
        $message->headers = self::parseHeaders($headerBlock);

        $parts = ['plain' => [], 'html' => []];
        self::collectText($message->headers, $body, $parts, 0);

        $text = $parts['plain'] !== []
            ? implode("\n\n", $parts['plain'])
            : implode("\n\n", array_map([self::class, 'htmlToText'], $parts['html']));

        $message->text = self::normalizeText($text);

        return $message;
    }

    // ------------------------------------------------------------------
    // Toegang
    // ------------------------------------------------------------------

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : self::decodeHeader($values[0]);
    }

    public function rawHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : trim($values[0]);
    }

    public function subject(): string
    {
        return trim((string) $this->header('subject'));
    }

    /** @return array{email: ?string, name: ?string} */
    public function from(): array
    {
        return self::parseAddress($this->header('from'));
    }

    /** @return array{email: ?string, name: ?string} */
    public function replyTo(): array
    {
        return self::parseAddress($this->header('reply-to'));
    }

    public function messageId(): ?string
    {
        $value = $this->rawHeader('message-id');

        return $value ? trim($value) : null;
    }

    public function date(): ?\DateTimeImmutable
    {
        $value = $this->rawHeader('date');
        if (! $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable(preg_replace('/\s*\([^)]*\)\s*$/', '', $value));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Reden waarom dit een automatisch bericht is, of null. */
    public function automatedReason(): ?string
    {
        $autoSubmitted = strtolower((string) $this->rawHeader('auto-submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return 'Automatisch verstuurd bericht';
        }

        $precedence = strtolower((string) $this->rawHeader('precedence'));
        if (in_array($precedence, ['bulk', 'list', 'junk', 'auto_reply'], true)) {
            return 'Massamail / nieuwsbrief';
        }

        if ($this->rawHeader('list-id') || $this->rawHeader('list-unsubscribe')) {
            return 'Nieuwsbrief of mailinglijst';
        }

        if ($this->rawHeader('x-autoreply') || $this->rawHeader('x-autorespond') || $this->rawHeader('x-auto-response-suppress')) {
            return 'Automatisch antwoord';
        }

        $from = strtolower((string) ($this->from()['email'] ?? ''));
        if ($from === '' || preg_match('/(^|[._-])(no-?reply|do-?not-?reply|mailer-daemon|postmaster|bounce[s]?)([._+-]|@)/', $from)) {
            return 'Afzender aanvaardt geen antwoorden';
        }

        $subject = strtolower($this->subject());
        if (preg_match('/^(automatisch antwoord|out of office|afwezig|auto(matic)? ?reply|undeliverable|delivery status notification|niet bezorgd)/', $subject)) {
            return 'Afwezigheidsmelding of foutbericht';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Parsing
    // ------------------------------------------------------------------

    private static function splitHeaderBody(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");

        if ($pos === false) {
            return [$raw, ''];
        }

        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    private static function parseHeaders(string $block): array
    {
        $headers = [];
        $block = preg_replace("/\n[ \t]+/", ' ', $block); // unfold

        foreach (explode("\n", (string) $block) as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $pos)));
            $headers[$name][] = trim(substr($line, $pos + 1));
        }

        return $headers;
    }

    /** Parameters van een header zoals Content-Type: text/plain; charset="utf-8" */
    private static function headerParams(?string $value): array
    {
        $value = (string) $value;
        $parts = explode(';', $value);
        $result = ['value' => strtolower(trim(array_shift($parts) ?? ''))];

        if (preg_match_all('/([a-zA-Z0-9*_-]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;\s]+)/', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result[strtolower(rtrim($match[1], '*'))] = trim($match[2], '"');
            }
        }

        return $result;
    }

    private static function collectText(array $headers, string $body, array &$parts, int $depth): void
    {
        if ($depth > 10) {
            return;
        }

        $type = self::headerParams($headers['content-type'][0] ?? 'text/plain');
        $disposition = self::headerParams($headers['content-disposition'][0] ?? '');
        $encoding = strtolower(trim($headers['content-transfer-encoding'][0] ?? '7bit'));

        if (str_starts_with($type['value'], 'multipart/') && ! empty($type['boundary'])) {
            foreach (self::splitMultipart($body, $type['boundary']) as $part) {
                [$partHeaderBlock, $partBody] = self::splitHeaderBody($part);
                self::collectText(self::parseHeaders($partHeaderBlock), $partBody, $parts, $depth + 1);
            }

            return;
        }

        if ($disposition['value'] === 'attachment') {
            return;
        }

        if (in_array($type['value'], ['text/plain', 'text/html', ''], true)) {
            $decoded = self::decodeBody($body, $encoding);
            $decoded = self::toUtf8($decoded, $type['charset'] ?? null);

            if ($type['value'] === 'text/html') {
                $parts['html'][] = $decoded;
            } else {
                $parts['plain'][] = $decoded;
            }

            return;
        }

        if ($type['value'] === 'message/rfc822') {
            // doorgestuurd bericht: niet verder uitpakken
            return;
        }
    }

    private static function splitMultipart(string $body, string $boundary): array
    {
        $parts = preg_split('/^--' . preg_quote($boundary, '/') . '(--)?[ \t]*$/m', $body) ?: [];
        array_shift($parts); // preamble

        return array_values(array_filter(array_map(fn ($p) => ltrim($p, "\n"), $parts), fn ($p) => trim($p) !== ''));
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private static function toUtf8(string $value, ?string $charset): string
    {
        $charset = strtoupper(trim((string) $charset));

        if ($charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII') {
            return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        try {
            $converted = @mb_convert_encoding($value, 'UTF-8', $charset);

            return is_string($converted) ? $converted : $value;
        } catch (\Throwable) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
    }

    public static function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        // Witruimte tussen twee encoded-words wordt genegeerd (RFC 2047)
        $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? $decoded : mb_decode_mimeheader($value);
    }

    /** @return array{email: ?string, name: ?string} */
    public static function parseAddress(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['email' => null, 'name' => null];
        }

        if (preg_match('/^(.*)<([^>]+)>/', $value, $m)) {
            $name = trim(trim($m[1]), "\"' ");

            return ['email' => strtolower(trim($m[2])), 'name' => $name !== '' ? $name : null];
        }

        if (preg_match('/[^\s<>"]+@[^\s<>"]+/', $value, $m)) {
            return ['email' => strtolower(trim($m[0], '.,;')), 'name' => null];
        }

        return ['email' => null, 'name' => null];
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|table|blockquote)>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<li[^>]*>#i', '- ', $html) ?? $html;
        $text = strip_tags($html);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Enkel het nieuwe deel van de mail (zonder geciteerde vorige berichten), voor de AI.
     */
    public static function stripQuoted(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^(On|Op|Le|Am)\s.{4,250}\b(wrote|schreef|a écrit|schrieb)\b.*:$/iu', $trimmed)
                || preg_match('/^-{2,}\s*(Original Message|Oorspronkelijk bericht|Origineel bericht|Doorgestuurd bericht|Forwarded message)/i', $trimmed)
                || preg_match('/^(From|Van|De|Von)\s*:\s*.+@/i', $trimmed)) {
                break;
            }

            if (str_starts_with($trimmed, '>')) {
                continue;
            }

            $out[] = $line;
        }

        $result = trim(implode("\n", $out));

        return $result !== '' ? $result : $text;
    }
}

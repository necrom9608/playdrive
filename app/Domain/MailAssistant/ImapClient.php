<?php

namespace App\Domain\MailAssistant;

use RuntimeException;

/**
 * Minimale IMAP-client (RFC 3501) over een socket — geen ext-imap of extra package nodig.
 * Ondersteunt enkel wat de mailassistent gebruikt: LOGIN, LIST, SELECT, UID SEARCH,
 * UID FETCH, UID STORE, APPEND en LOGOUT.
 */
class ImapClient
{
    /** @var resource|null */
    private $stream = null;

    private int $tagCounter = 0;

    private ?int $uidValidity = null;

    private ?int $uidNext = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 993,
        private readonly string $encryption = 'ssl', // ssl | tls | none
        private readonly int $timeout = 30,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    // ------------------------------------------------------------------
    // Verbinding
    // ------------------------------------------------------------------

    public function connect(): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $this->host,
            ],
        ]);

        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $errno = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $stream) {
            throw new RuntimeException("Kan geen verbinding maken met {$this->host}:{$this->port} ({$errstr})");
        }

        stream_set_timeout($stream, $this->timeout);
        $this->stream = $stream;

        $greeting = $this->readLine();
        if (! str_starts_with($greeting, '* OK') && ! str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Onverwacht antwoord van de mailserver: ' . trim($greeting));
        }

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS');
            if (! @stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS mislukt.');
            }
        }
    }

    public function login(string $username, string $password): void
    {
        try {
            $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password));
        } catch (RuntimeException $e) {
            throw new RuntimeException('Aanmelden op de mailbox mislukt — controleer gebruikersnaam en wachtwoord.');
        }
    }

    public function logout(): void
    {
        if ($this->stream) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
                // maakt niet uit
            }
        }

        $this->close();
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->stream = null;
    }

    // ------------------------------------------------------------------
    // Mappen
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{name: string, attributes: string[]}>
     */
    public function listFolders(): array
    {
        $response = $this->command('LIST "" "*"');
        $folders = [];

        foreach ($response['untagged'] as $item) {
            if (! preg_match('/^\* LIST \(([^)]*)\) (NIL|"(?:[^"\\\\]|\\\\.)*") (.+)$/s', $item['text'], $m)) {
                continue;
            }

            $name = trim($m[3]);
            if ($item['literals'] !== [] && str_contains($name, '{')) {
                $name = end($item['literals']);
            } elseif (str_starts_with($name, '"')) {
                $name = stripcslashes(substr($name, 1, -1));
            }

            $folders[] = [
                'name' => $name,
                'attributes' => preg_split('/\s+/', trim($m[1])) ?: [],
            ];
        }

        return $folders;
    }

    /** Zoekt de map voor verzonden berichten (\Sent of een gekende naam). */
    public function findSentFolder(): ?string
    {
        $folders = $this->listFolders();

        foreach ($folders as $folder) {
            if (in_array('\\Sent', $folder['attributes'], true)) {
                return $folder['name'];
            }
        }

        $known = ['sent', 'verzonden', 'inbox.sent', 'inbox/sent', 'sent items', 'sent messages', 'verzonden items', '[gmail]/sent mail', 'inbox.verzonden'];
        foreach ($folders as $folder) {
            if (in_array(strtolower($folder['name']), $known, true)) {
                return $folder['name'];
            }
        }

        return null;
    }

    public function select(string $folder): void
    {
        $response = $this->command('SELECT ' . $this->quote($folder));
        $this->uidValidity = null;
        $this->uidNext = null;

        foreach ($response['untagged'] as $item) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $item['text'], $m)) {
                $this->uidValidity = (int) $m[1];
            }
            if (preg_match('/\[UIDNEXT (\d+)\]/i', $item['text'], $m)) {
                $this->uidNext = (int) $m[1];
            }
        }
    }

    public function uidValidity(): ?int
    {
        return $this->uidValidity;
    }

    public function uidNext(): ?int
    {
        return $this->uidNext;
    }

    // ------------------------------------------------------------------
    // Berichten
    // ------------------------------------------------------------------

    /** @return int[] UID's groter dan $afterUid */
    public function uidsAfter(int $afterUid): array
    {
        $response = $this->command('UID SEARCH UID ' . ($afterUid + 1) . ':*');

        return array_values(array_filter($this->parseSearch($response), fn (int $uid) => $uid > $afterUid));
    }

    /** @return int[] UID's van berichten sinds een datum */
    public function uidsSince(\DateTimeInterface $date): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $imapDate = $date->format('j') . '-' . $months[(int) $date->format('n') - 1] . '-' . $date->format('Y');

        return $this->parseSearch($this->command('UID SEARCH SINCE ' . $imapDate));
    }

    /**
     * Haalt één volledig bericht op zonder het als gelezen te markeren.
     *
     * @return array{uid: int, raw: string, internal_date: ?string, flags: string[]}|null
     */
    public function fetchMessage(int $uid): ?array
    {
        $response = $this->command("UID FETCH {$uid} (UID FLAGS INTERNALDATE BODY.PEEK[])");

        foreach ($response['untagged'] as $item) {
            if (! preg_match('/^\* \d+ FETCH /', $item['text'])) {
                continue;
            }
            if (! preg_match('/UID (\d+)/', $item['text'], $m) || (int) $m[1] !== $uid) {
                continue;
            }

            preg_match('/INTERNALDATE "([^"]+)"/', $item['text'], $dateMatch);
            preg_match('/FLAGS \(([^)]*)\)/', $item['text'], $flagMatch);

            return [
                'uid' => $uid,
                'raw' => $item['literals'][0] ?? '',
                'internal_date' => $dateMatch[1] ?? null,
                'flags' => isset($flagMatch[1]) ? (preg_split('/\s+/', trim($flagMatch[1])) ?: []) : [],
            ];
        }

        return null;
    }

    public function addFlags(int $uid, array $flags): void
    {
        $this->command("UID STORE {$uid} +FLAGS (" . implode(' ', $flags) . ')');
    }

    /** Plaatst een bericht in een map (bv. Verzonden). */
    public function append(string $folder, string $rawMessage, array $flags = ['\\Seen']): void
    {
        $rawMessage = preg_replace("/\r?\n/", "\r\n", $rawMessage);
        $tag = $this->nextTag();
        $length = strlen($rawMessage);

        $this->write("{$tag} APPEND " . $this->quote($folder) . ' (' . implode(' ', $flags) . ") {{$length}}\r\n");

        $line = $this->readLine();
        if (! str_starts_with($line, '+')) {
            throw new RuntimeException('APPEND geweigerd: ' . trim($line));
        }

        $this->write($rawMessage . "\r\n");
        $this->readResponse($tag);
    }

    // ------------------------------------------------------------------
    // Protocol
    // ------------------------------------------------------------------

    /**
     * @return array{status: string, text: string, untagged: array<int, array{text: string, literals: string[]}>}
     */
    public function command(string $command): array
    {
        $tag = $this->nextTag();
        $this->write("{$tag} {$command}\r\n");

        return $this->readResponse($tag);
    }

    private function readResponse(string $tag): array
    {
        $untagged = [];

        while (true) {
            $item = $this->readItem();
            $text = $item['text'];

            if (str_starts_with($text, $tag . ' ')) {
                $rest = substr($text, strlen($tag) + 1);
                $status = strtoupper(strtok($rest, ' ') ?: '');

                if ($status !== 'OK') {
                    throw new RuntimeException('Mailserver: ' . trim($rest));
                }

                return ['status' => $status, 'text' => trim($rest), 'untagged' => $untagged];
            }

            $untagged[] = $item;
        }
    }

    /**
     * Leest één antwoordregel, inclusief eventuele literals ({n}\r\n + n bytes).
     *
     * @return array{text: string, literals: string[]}
     */
    private function readItem(): array
    {
        $text = '';
        $literals = [];

        while (true) {
            $line = $this->readLine();

            if (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $text .= substr($line, 0, -2);
                $literals[] = $this->readBytes((int) $m[1]);
                continue;
            }

            $text .= rtrim($line, "\r\n");

            return ['text' => $text, 'literals' => $literals];
        }
    }

    private function readLine(): string
    {
        $this->assertConnected();
        $line = fgets($this->stream);

        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new RuntimeException(! empty($meta['timed_out'])
                ? 'Time-out bij het lezen van de mailserver.'
                : 'Verbinding met de mailserver verbroken.');
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $this->assertConnected();
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->stream, min(65536, $length - strlen($data)));

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if (! empty($meta['timed_out']) || feof($this->stream)) {
                    throw new RuntimeException('Bericht onvolledig ontvangen van de mailserver.');
                }
                continue;
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function write(string $data): void
    {
        $this->assertConnected();

        $written = 0;
        $length = strlen($data);

        while ($written < $length) {
            $result = fwrite($this->stream, substr($data, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Schrijven naar de mailserver mislukt.');
            }
            $written += $result;
        }
    }

    private function parseSearch(array $response): array
    {
        $uids = [];

        foreach ($response['untagged'] as $item) {
            if (preg_match('/^\* SEARCH(.*)$/i', $item['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    if (ctype_digit($uid)) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        sort($uids);

        return array_values(array_unique($uids));
    }

    private function quote(string $value): string
    {
        if (preg_match('/[\r\n\x00]/', $value)) {
            throw new RuntimeException('Ongeldig teken in IMAP-parameter.');
        }

        return '"' . addcslashes($value, "\"\\") . '"';
    }

    private function nextTag(): string
    {
        return 'PD' . str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);
    }

    private function assertConnected(): void
    {
        if (! is_resource($this->stream)) {
            throw new RuntimeException('Geen verbinding met de mailserver.');
        }
    }
}

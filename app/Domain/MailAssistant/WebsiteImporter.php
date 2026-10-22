<?php

namespace App\Domain\MailAssistant;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kennisbank vullen vanuit de website van de zaak:
 * 1. pages()   — pagina's zoeken (sitemap, anders links op de homepage)
 * 2. extract() — een handvol pagina's lezen en Claude er vraag/antwoord-paren van laten maken
 *
 * Enkel publieke http(s)-adressen op hetzelfde domein; geen interne/private IP's.
 */
class WebsiteImporter
{
    private const MAX_PAGES = 40;
    private const MAX_PAGE_CHARS = 7000;
    private const USER_AGENT = 'PlaydriveBot/1.0 (+https://playdrive.be)';

    private const SKIP_PATTERNS = [
        '#/wp-(content|admin|includes|json|login)#i',
        '#/(feed|tag|author|category|page/\d+|cart|checkout|my-account|winkelmand|afrekenen)(/|$)#i',
        '#\.(jpe?g|png|gif|webp|svg|pdf|zip|mp4|css|js|xml)$#i',
        '#[?&](replytocom|share|add-to-cart)=#i',
    ];

    /**
     * @return array<int, array{url: string, title: ?string}>
     */
    public function pages(string $startUrl): array
    {
        $start = $this->normalizeUrl($startUrl);
        $host = parse_url($start, PHP_URL_HOST);
        $root = parse_url($start, PHP_URL_SCHEME) . '://' . $host;

        $urls = [];
        foreach (['/wp-sitemap.xml', '/sitemap_index.xml', '/sitemap.xml'] as $path) {
            $urls = $this->fromSitemap($root . $path, $host);
            if ($urls !== []) {
                break;
            }
        }

        if ($urls === []) {
            $urls = $this->linksOnPage($start, $host);
        }

        array_unshift($urls, $start);

        $urls = collect($urls)
            ->map(fn ($u) => (string) strtok($u, '#'))
            ->unique(fn ($u) => rtrim($u, '/'))
            ->reject(fn ($u) => $this->shouldSkip($u))
            ->take(self::MAX_PAGES)
            ->values();

        return $urls->map(fn ($u) => ['url' => $u, 'title' => $this->titleFromUrl($u, $root)])->all();
    }

    /**
     * @param  string[]  $urls          max. een handvol per aanroep
     * @param  string[]  $knownQuestions vragen die al bestaan of al voorgesteld zijn (niet herhalen)
     * @return array<int, array{question: string, answer: string, source_url: ?string}>
     */
    public function extract(array $urls, string $businessName, array $knownQuestions = []): array
    {
        $apiKey = (string) config('services.anthropic.key');
        if ($apiKey === '') {
            throw new RuntimeException('Geen Claude API-sleutel ingesteld (ANTHROPIC_API_KEY).');
        }

        $pages = [];
        foreach (array_slice($urls, 0, 6) as $url) {
            try {
                $text = $this->pageText($this->normalizeUrl($url));
            } catch (\Throwable) {
                continue;
            }

            if (mb_strlen($text) >= 80) {
                $pages[] = "### PAGINA: {$url}\n" . mb_substr($text, 0, self::MAX_PAGE_CHARS);
            }
        }

        if ($pages === []) {
            return [];
        }

        $known = $knownQuestions === []
            ? '(nog geen)'
            : implode("\n", array_map(fn ($q) => '- ' . mb_substr((string) $q, 0, 200), array_slice($knownQuestions, 0, 150)));

        $system = <<<PROMPT
Je maakt een kennisbank voor een mailassistent die klantvragen beantwoordt namens {$businessName}.
Haal uit de websiteteksten de feiten waarmee je typische klantvragen kan beantwoorden: formules en wat erin zit,
leeftijden, duur, regels, wat mag en niet mag (eten, drank, taart, sokken), groepsgroottes, betaling, cadeaubonnen,
abonnementen, toegankelijkheid, parking, ligging, annulering en reservatie-werkwijze.

Regels:
- Enkel feiten die letterlijk in de tekst staan. Niets verzinnen of aanvullen.
- GEEN openingsuren en GEEN concrete prijzen of bedragen: die komen live uit Playdrive (de website kan verouderd zijn).
  Je mag wel vermelden hoe iets geprijsd wordt (bv. "per persoon", "per uur") zonder het bedrag.
- Geen tijdelijke meldingen (bv. "tijdelijk gesloten van ... tot ...").
- Formuleer de vraag zoals een klant ze zou mailen; het antwoord kort en feitelijk (1 à 4 zinnen), in het Nederlands.
- Herhaal geen vragen die al bestaan (lijst hieronder) en geen dubbels onderling.
- Max. 25 paren.

Antwoord met exact één JSON-object, zonder uitleg of markdown:
{"items": [{"question": "...", "answer": "...", "source_url": "<url van de pagina>"}]}

Bestaande vragen:
{$known}
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-sonnet-4-5'),
                'max_tokens' => 4000,
                'system' => $system,
                'messages' => [[
                    'role' => 'user',
                    'content' => "WEBSITETEKSTEN:\n\n" . implode("\n\n", $pages),
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Claude API: ' . ($response->json('error.message') ?? 'HTTP ' . $response->status()));
        }

        $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $data = ($start !== false && $end !== false) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;

        if (! is_array($data) || ! is_array($data['items'] ?? null)) {
            throw new RuntimeException('Onverwacht antwoord van Claude.');
        }

        return collect($data['items'])
            ->filter(fn ($i) => is_array($i) && filled($i['question'] ?? null) && filled($i['answer'] ?? null))
            ->map(fn ($i) => [
                'question' => mb_substr(trim((string) $i['question']), 0, 500),
                'answer' => mb_substr(trim((string) $i['answer']), 0, 4000),
                'source_url' => isset($i['source_url']) ? mb_substr((string) $i['source_url'], 0, 500) : null,
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Ophalen
    // ------------------------------------------------------------------

    private function fromSitemap(string $url, string $host, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        try {
            $xml = $this->get($url);
        } catch (\Throwable) {
            return [];
        }

        if (! str_contains($xml, '<loc>')) {
            return [];
        }

        preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $xml, $m);
        $locs = array_map(fn ($l) => html_entity_decode(trim($l)), $m[1] ?? []);

        // Sitemap-index: enkel de sitemaps met pagina's/berichten volgen
        if (str_contains($xml, '<sitemapindex')) {
            $urls = [];
            foreach ($locs as $loc) {
                if (preg_match('#(users|taxonom|tags|categor|author|product_cat|attachment)#i', $loc)) {
                    continue;
                }
                $urls = array_merge($urls, $this->fromSitemap($loc, $host, $depth + 1));
                if (count($urls) >= self::MAX_PAGES * 2) {
                    break;
                }
            }

            return $urls;
        }

        return array_values(array_filter($locs, fn ($l) => parse_url($l, PHP_URL_HOST) === $host));
    }

    private function linksOnPage(string $url, string $host): array
    {
        try {
            $html = $this->get($url);
        } catch (\Throwable) {
            return [];
        }

        preg_match_all('#href\s*=\s*["\']([^"\'#]+)#i', $html, $m);

        return collect($m[1] ?? [])
            ->map(function ($href) use ($url) {
                if (str_starts_with($href, '//')) {
                    return parse_url($url, PHP_URL_SCHEME) . ':' . $href;
                }
                if (str_starts_with($href, '/')) {
                    return parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . $href;
                }

                return $href;
            })
            ->filter(fn ($u) => parse_url($u, PHP_URL_HOST) === $host)
            ->values()
            ->all();
    }

    private function pageText(string $url): string
    {
        $html = $this->get($url);

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        foreach (['//script', '//style', '//noscript', '//svg', '//form', '//nav', '//header', '//footer', '//iframe',
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' cookie')]"] as $query) {
            foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $main = $xpath->query('//main')->item(0)
            ?? $xpath->query('//article')->item(0)
            ?? $xpath->query('//body')->item(0);

        if (! $main) {
            return '';
        }

        // Blokelementen op een eigen regel
        foreach (iterator_to_array($xpath->query('.//p|.//div|.//li|.//h1|.//h2|.//h3|.//h4|.//tr|.//br', $main) ?: []) as $node) {
            $node->parentNode?->insertBefore($dom->createTextNode("\n"), $node);
        }

        $text = html_entity_decode($main->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n+/", "\n", $text) ?? $text;

        return trim($text);
    }

    /** GET met handmatige redirects (max. 3), telkens met controle op publiek adres. */
    private function get(string $url): string
    {
        for ($i = 0; $i <= 3; $i++) {
            $this->assertPublic($url);

            $response = Http::withUserAgent(self::USER_AGENT)
                ->withOptions(['allow_redirects' => false])
                ->timeout(15)
                ->get($url);

            if (in_array($response->status(), [301, 302, 303, 307, 308], true) && $response->header('Location')) {
                $location = $response->header('Location');
                $url = str_starts_with($location, 'http')
                    ? $location
                    : parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/' . ltrim($location, '/');
                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException("{$url} gaf HTTP {$response->status()}");
            }

            return mb_substr($response->body(), 0, 2_000_000);
        }

        throw new RuntimeException('Te veel doorverwijzingen.');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Ongeldig webadres.');
        }

        return $url;
    }

    private function assertPublic(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Enkel http(s)-adressen.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new RuntimeException("Domein {$host} niet gevonden.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Dit adres is niet publiek bereikbaar.');
            }
        }
    }

    private function shouldSkip(string $url): bool
    {
        foreach (self::SKIP_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    private function titleFromUrl(string $url, string $root): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return 'Startpagina';
        }

        $last = basename($path);
        $last = preg_replace('/-\d+$/', '', $last) ?? $last;

        return ucfirst(str_replace(['-', '_'], ' ', urldecode($last)));
    }
}

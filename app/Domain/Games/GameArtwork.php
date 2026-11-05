<?php

namespace App\Domain\Games;

use App\Models\Game;
use App\Models\Setup;
use App\Models\VenueDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Banners voor de tegels in Frontdesk → Zaal.
 *
 * Bronnen:
 *   Steam            header.jpg van de Steam-CDN (460×215), enkel voor Steam-games
 *   SteamGridDB      banners 460×215 / 920×430 (ook TeknoParrot en andere niet-Steam-games);
 *                    vraagt een gratis API-key: STEAMGRIDDB_API_KEY in .env
 *   Arcade Database  echte arcade-marquee op basis van de MAME-romnaam (adb.arcadeitalia.net)
 *   Upload           eigen bestand (bv. DANCERUSH, of een foto van een couch-setup)
 *
 * Gekozen beelden worden gedownload naar de disk "local" (banners/{tenant}/...) en
 * uitgeleverd via /media/banners/{game|setup}/{id}, zodat de kassa niet afhangt van externe sites.
 *
 * Automatisch (auto / autoSetup) laat zelf gekozen of geüploade banners (banner_manual) en
 * bewust verwijderde banners (banner_source = removed) altijd staan.
 *
 * Arcadekasten: de MAME-romnaam wordt gezocht door Claude een paar romnamen te laten voorstellen
 * (ANTHROPIC_API_KEY, dezelfde als de mailassistent) en elke gok bij Arcade Database te controleren:
 * enkel een romnaam waarvan de titel overeenkomt met de naam van de kast wordt aanvaard.
 */
class GameArtwork
{
    public const DISK = 'local';

    public const SOURCES = [
        'steam'       => 'Steam',
        'steamgriddb' => 'SteamGridDB',
        'arcadedb'    => 'Arcade Database',
        'upload'      => 'Eigen upload',
    ];

    /** Steam-banner: 460×215. */
    private const STEAM_RATIO = 2.14;

    private const MAX_BYTES = 8 * 1024 * 1024;

    private const UA = 'PlayDrive/1.0 (+https://playdrive.be; banners)';

    /** Enkel van deze hosts downloaden we (de URL komt uit de backoffice). */
    private const ALLOWED_HOSTS = [
        'cdn.cloudflare.steamstatic.com',
        'shared.cloudflare.steamstatic.com',
        'cdn.akamai.steamstatic.com',
        'shared.akamai.steamstatic.com',
        'shared.fastly.steamstatic.com',
        'steamcdn-a.akamaihd.net',
        'cdn2.steamgriddb.com',
        'cdn.steamgriddb.com',
        'adb.arcadeitalia.net',
    ];

    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    // ---------------------------------------------------------------- uitlevering

    /**
     * Banner om te tonen, of null.
     * Steam-games zonder opgeslagen banner vallen terug op de Steam-CDN (tenzij bewust verwijderd).
     *
     * @return array{url: string, ratio: float, source: string}|null
     */
    public static function bannerFor(Model $target): ?array
    {
        if ($target->banner_path) {
            return [
                'url'    => self::publicUrl($target),
                'ratio'  => (float) ($target->banner_ratio ?: self::STEAM_RATIO),
                'source' => (string) $target->banner_source,
            ];
        }

        if ($target instanceof Game && $target->banner_source !== 'removed' && $target->coverUrl()) {
            return ['url' => $target->coverUrl(), 'ratio' => self::STEAM_RATIO, 'source' => 'steam'];
        }

        return null;
    }

    public static function publicUrl(Model $target): ?string
    {
        if (! $target->banner_path) {
            return null;
        }

        return url('/media/banners/'.self::kind($target).'/'.$target->getKey())
            .'?v='.substr(md5((string) $target->banner_path), 0, 10);
    }

    public static function kind(Model $target): string
    {
        return $target instanceof Setup ? 'setup' : 'game';
    }

    // ---------------------------------------------------------------- automatisch

    /**
     * Zelf een banner zoeken voor een game (eerste bruikbare kandidaat).
     * Laat eigen uploads en bewust verwijderde banners met rust. → ok | not_found | skipped
     */
    public function auto(Game $game, bool $force = false): string
    {
        if ($game->banner_manual || in_array($game->banner_source, ['upload', 'removed'], true)) {
            return 'skipped';
        }
        if ($game->banner_path && ! $force) {
            return 'skipped';
        }

        foreach (array_slice($this->candidatesForGame($game), 0, 3) as $candidate) {
            if ($this->storeFromUrl($game, $candidate['url'], $candidate['source'])) {
                return 'ok';
            }
        }

        $game->forceFill(['banner_fetched_at' => now()])->save();

        return 'not_found';
    }

    /**
     * Banner voor een speelplek, volgens het type:
     *   arcade / pinball         echte marquee via de MAME-romnaam (zelf gezocht), anders SteamGridDB op naam
     *   met Playscope-toestel    niets opslaan: de Zaal toont de game die speelt of laatst speelde
     *   console / vr / andere    SteamGridDB op de eerste game van de lijst
     * → ok | not_found | skipped
     */
    public function autoSetup(Setup $setup, bool $force = false): string
    {
        if ($setup->banner_manual || in_array($setup->banner_source, ['upload', 'removed'], true)) {
            return 'skipped';
        }
        if ($setup->banner_path && ! $force) {
            return 'skipped';
        }

        $firstGame = collect($setup->games ?? [])->map(fn ($g) => trim((string) $g))->filter()->first();
        $term = $firstGame ?: (string) $setup->name;
        $candidates = [];

        if (in_array($setup->type, ['arcade', 'pinball'], true)) {
            $mame = $setup->mame_name ?: $this->findMameName($term);
            if ($mame && $mame !== $setup->mame_name) {
                $setup->forceFill(['mame_name' => $mame])->save();
            }
            if ($mame) {
                $candidates = $this->arcadeDb($mame);
            }
            $candidates = [...$candidates, ...array_slice($this->steamGridDb($term, null), 0, 3)];
        } else {
            $hasPlayscope = VenueDevice::query()->where('setup_id', $setup->id)->where('type', 'playscope')->exists();
            if ($hasPlayscope || ! $firstGame) {
                $setup->forceFill(['banner_fetched_at' => now()])->save();

                return 'skipped';
            }
            $candidates = array_slice($this->steamGridDb($firstGame, null), 0, 3);
        }

        foreach ($candidates as $candidate) {
            if ($this->storeFromUrl($setup, $candidate['url'], $candidate['source'])) {
                return 'ok';
            }
        }

        $setup->forceFill(['banner_fetched_at' => now()])->save();

        return 'not_found';
    }

    /**
     * MAME-romnaam voor een arcadespel: Claude stelt er een paar voor, Arcade Database bevestigt.
     * Enkel een romnaam waarvan de titel bij ADB overeenkomt met de naam wordt teruggegeven.
     */
    public function findMameName(string $title): ?string
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        foreach ($this->mameGuesses($title) as $guess) {
            $row = $this->arcadeDbRow($guess);
            if ($row && $this->titlesMatch($title, (string) ($row['title'] ?? ''))) {
                return $guess;
            }
            usleep(150_000);
        }

        return null;
    }

    /** @return string[] */
    private function mameGuesses(string $title): array
    {
        $key = (string) config('services.anthropic.key');
        if ($key === '') {
            return [];
        }

        return Cache::remember('mame-guess:'.md5(mb_strtolower($title)), now()->addDays(30), function () use ($key, $title) {
            try {
                $response = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                    ->timeout(30)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => (string) config('services.anthropic.model', 'claude-sonnet-4-5'),
                        'max_tokens' => 200,
                        'system' => 'Je kent de MAME-romnamen (short names) van arcadespellen. Antwoord uitsluitend met JSON, zonder uitleg.',
                        'messages' => [[
                            'role' => 'user',
                            'content' => 'Welke MAME short names horen bij het arcadespel "'.$title.'"? '
                                .'Geef maximaal 5 kandidaten, meest waarschijnlijke eerst (parent-set eerst, daarna varianten). '
                                .'Zit het spel niet in MAME, geef dan een lege lijst. '
                                .'Formaat: {"romnames": ["...", "..."]}',
                        ]],
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $text = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
                if (! preg_match('/\{.*\}/s', $text, $m)) {
                    return [];
                }

                return collect((array) (json_decode($m[0], true)['romnames'] ?? []))
                    ->map(fn ($n) => strtolower(trim((string) $n)))
                    ->filter(fn ($n) => preg_match('/^[a-z0-9_]{1,32}$/', $n))
                    ->unique()
                    ->take(5)
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * Komt de naam van de kast overeen met de titel bij Arcade Database?
     * "Motocross Go! DX" ↔ "Motocross Go! (MG3 Ver. A)", "Harley-Davidson" ↔ "Harley-Davidson & L.A. Riders (Revision A)".
     */
    public function titlesMatch(string $name, string $adbTitle): bool
    {
        $words = function (string $text): array {
            $text = preg_replace('/\([^)]*\)|\[[^\]]*\]/u', ' ', mb_strtolower($text));
            $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $text);

            $roman = ['ii' => '2', 'iii' => '3', 'iv' => '4'];
            $list = array_map(fn ($w) => $roman[$w] ?? $w, explode(' ', trim((string) $text)));

            return array_values(array_filter($list, fn ($w) => mb_strlen($w) >= 2 || ctype_digit($w)));
        };

        $a = $words($name);
        $b = $words($adbTitle);
        if (! $a || ! $b) {
            return false;
        }

        // Varianten zoals "DX" / "Deluxe" / "Twin" mogen ontbreken aan één kant.
        $ignore = ['dx', 'deluxe', 'sd', 'twin', 'upright', 'cabinet', 'kast', 'the', 'arcade'];
        $a2 = array_values(array_diff($a, $ignore)) ?: $a;
        $b2 = array_values(array_diff($b, $ignore)) ?: $b;

        // Volgnummers moeten gelijk zijn: "Time Crisis 2" is niet "Time Crisis".
        $numbers = fn (array $w) => collect($w)
            ->filter(fn ($x) => ctype_digit($x))
            ->unique()->sort()->values()->all();
        if ($numbers($a2) !== $numbers($b2)) {
            return false;
        }

        $common = array_intersect($a2, $b2);
        if (! collect($common)->contains(fn ($w) => mb_strlen($w) >= 4 || ctype_digit($w))) {
            return false;
        }

        return ! array_diff($a2, $b2) || ! array_diff($b2, $a2);
    }

    // ---------------------------------------------------------------- kandidaten

    /** @return array<int, array{source: string, label: string, url: string, thumb: string, width: ?int, height: ?int}> */
    public function candidatesForGame(Game $game, ?string $query = null, ?string $mameName = null): array
    {
        $list = [];

        if ($mameName) {
            $list = [...$list, ...$this->arcadeDb($mameName)];
        }

        if ($game->source === 'steam' && ctype_digit((string) $game->external_id)) {
            $list = [...$list, ...$this->steam((string) $game->external_id)];
        }

        $steamAppId = $game->source === 'steam' ? (string) $game->external_id : null;
        $list = [...$list, ...$this->steamGridDb($query ?: $game->name, $query ? null : $steamAppId)];

        return $this->unique($list);
    }

    /** Voor een speelplek: standaard zoeken op de (eerste) game van de setup, anders de naam. */
    public function candidatesForSetup(Setup $setup, ?string $query = null, ?string $mameName = null): array
    {
        $term = $query ?: (collect($setup->games ?? [])->filter()->first() ?: $setup->name);
        $list = [];

        if ($mameName) {
            $list = [...$list, ...$this->arcadeDb($mameName)];
        }

        $list = [...$list, ...$this->steamGridDb((string) $term, null)];

        return $this->unique($list);
    }

    public static function steamGridDbConfigured(): bool
    {
        return filled(config('services.steamgriddb.key'));
    }

    private function steam(string $appId): array
    {
        return [[
            'source' => 'steam',
            'label'  => 'Steam',
            'url'    => "https://cdn.cloudflare.steamstatic.com/steam/apps/{$appId}/header.jpg",
            'thumb'  => "https://cdn.cloudflare.steamstatic.com/steam/apps/{$appId}/header.jpg",
            'width'  => 460,
            'height' => 215,
        ]];
    }

    private function steamGridDb(string $term, ?string $steamAppId): array
    {
        $key = config('services.steamgriddb.key');
        $term = trim($term);
        if (! $key || $term === '') {
            return [];
        }

        return Cache::remember('sgdb:'.md5($term.'|'.$steamAppId), now()->addDay(), function () use ($key, $term, $steamAppId) {
            try {
                $http = fn () => Http::withToken($key)->withUserAgent(self::UA)->acceptJson()->timeout(10);
                $base = 'https://www.steamgriddb.com/api/v2';

                $gameId = null;
                if ($steamAppId) {
                    $gameId = $http()->get("{$base}/games/steam/{$steamAppId}")->json('data.id');
                }
                if (! $gameId) {
                    $gameId = $http()->get("{$base}/search/autocomplete/".rawurlencode($term))->json('data.0.id');
                }
                if (! $gameId) {
                    return [];
                }

                $grids = $http()->get("{$base}/grids/game/{$gameId}", [
                    'dimensions' => '460x215,920x430',
                    'types'      => 'static',
                    'nsfw'       => 'false',
                    'humor'      => 'false',
                ])->json('data') ?? [];

                return collect($grids)
                    ->filter(fn ($g) => is_array($g) && ! empty($g['url']))
                    ->sortByDesc(fn ($g) => (int) ($g['score'] ?? 0))
                    ->take(12)
                    ->map(fn ($g) => [
                        'source' => 'steamgriddb',
                        'label'  => 'SteamGridDB'.(! empty($g['style']) ? ' · '.$g['style'] : ''),
                        'url'    => $g['url'],
                        'thumb'  => $g['thumb'] ?? $g['url'],
                        'width'  => isset($g['width']) ? (int) $g['width'] : null,
                        'height' => isset($g['height']) ? (int) $g['height'] : null,
                    ])
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /** Arcade Database: marquee (en titelscherm als reserve) op basis van de MAME-romnaam. */
    private function arcadeDb(string $mameName): array
    {
        $mameName = strtolower(trim($mameName));
        if (! preg_match('/^[a-z0-9_]{1,32}$/', $mameName)) {
            return [];
        }

        $row = $this->arcadeDbRow($mameName);
        if (! $row) {
            return [];
        }

        return (function () use ($row) {
                $list = [];
                foreach (['url_image_marquee' => 'Arcade marquee', 'url_image_title' => 'Titelscherm'] as $field => $label) {
                    if (! empty($row[$field])) {
                        $url = preg_replace('#^http://#', 'https://', (string) $row[$field]);
                        $list[] = [
                            'source' => 'arcadedb',
                            'label'  => $label.(! empty($row['title']) ? ' · '.$row['title'] : ''),
                            'url'    => $url,
                            'thumb'  => $url,
                            'width'  => null,
                            'height' => null,
                        ];
                    }
                }

                return $list;
        })();
    }

    /** Eén spel bij Arcade Database (null = onbekend). */
    private function arcadeDbRow(string $mameName): ?array
    {
        $mameName = strtolower(trim($mameName));
        if (! preg_match('/^[a-z0-9_]{1,32}$/', $mameName)) {
            return null;
        }

        $row = Cache::remember('adb-row:'.$mameName, now()->addDays(7), function () use ($mameName) {
            try {
                $row = Http::withUserAgent(self::UA)->acceptJson()->timeout(10)
                    ->get('https://adb.arcadeitalia.net/service_scraper.php', [
                        'ajax'      => 'query_mame',
                        'game_name' => $mameName,
                    ])
                    ->json('result.0');

                return is_array($row) && ! empty($row['title']) ? $row : false;
            } catch (Throwable) {
                return null;   // niet cachen bij een netwerkfout
            }
        });

        if ($row === null) {
            Cache::forget('adb-row:'.$mameName);
        }

        return is_array($row) ? $row : null;
    }

    private function unique(array $list): array
    {
        return collect($list)->unique('url')->values()->all();
    }

    // ---------------------------------------------------------------- opslaan

    public function storeFromUrl(Model $target, string $url, string $source, bool $manual = false): bool
    {
        if (! $this->allowedUrl($url) || ! array_key_exists($source, self::SOURCES)) {
            return false;
        }

        try {
            // Geen redirects volgen: anders kan een toegelaten host naar een andere host doorsturen.
            $response = Http::withUserAgent(self::UA)->withoutRedirecting()->timeout(20)->get($url);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return $this->storeBytes($target, $response->body(), $source, $manual);
    }

    public function storeUpload(Model $target, UploadedFile $file): bool
    {
        return $this->storeBytes($target, (string) file_get_contents($file->getRealPath()), 'upload', true);
    }

    /** Verwijderen; bij een game wordt er daarna niet meer automatisch gezocht. */
    public function remove(Model $target): void
    {
        $this->deleteFile($target->banner_path);

        $target->forceFill([
            'banner_path'       => null,
            'banner_ratio'      => null,
            'banner_source'     => 'removed',
            'banner_manual'     => false,
            'banner_fetched_at' => now(),
        ])->save();
    }

    public function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            && in_array(strtolower($parts['host'] ?? ''), self::ALLOWED_HOSTS, true)
            && empty($parts['user'])
            && empty($parts['port']);
    }

    private function storeBytes(Model $target, string $bytes, string $source, bool $manual = false): bool
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return false;
        }

        $info = @getimagesizefromstring($bytes);
        $ext = $info ? (self::MIME_EXT[$info['mime'] ?? ''] ?? null) : null;
        if (! $info || ! $ext || $info[0] < 32 || $info[1] < 16) {
            return false;
        }

        $path = sprintf(
            'banners/%d/%s-%d-%s.%s',
            (int) $target->tenant_id,
            self::kind($target),
            (int) $target->getKey(),
            Str::lower(Str::random(8)),
            $ext
        );

        if (! Storage::disk(self::DISK)->put($path, $bytes)) {
            return false;
        }

        $old = $target->banner_path;

        $target->forceFill([
            'banner_path'       => $path,
            'banner_source'     => $source,
            'banner_ratio'      => round($info[0] / max(1, $info[1]), 2),
            'banner_manual'     => $manual,
            'banner_fetched_at' => now(),
        ])->save();

        if ($old && $old !== $path) {
            $this->deleteFile($old);
        }

        return true;
    }

    private function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'banners/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

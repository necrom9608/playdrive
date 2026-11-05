<?php

namespace App\Domain\Games;

use App\Models\Game;
use App\Models\Setup;
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
        if (in_array($game->banner_source, ['upload', 'removed'], true)) {
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

        return Cache::remember('adb:'.$mameName, now()->addDay(), function () use ($mameName) {
            try {
                $row = Http::withUserAgent(self::UA)->acceptJson()->timeout(10)
                    ->get('https://adb.arcadeitalia.net/service_scraper.php', [
                        'ajax'      => 'query_mame',
                        'game_name' => $mameName,
                    ])
                    ->json('result.0');

                if (! is_array($row)) {
                    return [];
                }

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
            } catch (Throwable) {
                return [];
            }
        });
    }

    private function unique(array $list): array
    {
        return collect($list)->unique('url')->values()->all();
    }

    // ---------------------------------------------------------------- opslaan

    public function storeFromUrl(Model $target, string $url, string $source): bool
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

        return $this->storeBytes($target, $response->body(), $source);
    }

    public function storeUpload(Model $target, UploadedFile $file): bool
    {
        return $this->storeBytes($target, (string) file_get_contents($file->getRealPath()), 'upload');
    }

    /** Verwijderen; bij een game wordt er daarna niet meer automatisch gezocht. */
    public function remove(Model $target): void
    {
        $this->deleteFile($target->banner_path);

        $target->forceFill([
            'banner_path'       => null,
            'banner_ratio'      => null,
            'banner_source'     => $target instanceof Game ? 'removed' : null,
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

    private function storeBytes(Model $target, string $bytes, string $source): bool
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

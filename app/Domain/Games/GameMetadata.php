<?php

namespace App\Domain\Games;

use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gegevens van Steam-games automatisch ophalen en invullen:
 *
 *   IGDB          max. spelers offline / online / LAN (als IGDB_CLIENT_ID ingesteld is) → spelers
 *   PCGamingWiki  aantal spelers op één scherm (Local_players), LAN en online   → spelers (reserve)
 *   SteamSpy      user tags van Steam (Racing, Party Game, Fighting, …)          → categorieën
 *   Steam Store   genres + categorieën (Shared/Split Screen, LAN PvP, …)         → reserve als de rest ontbreekt
 *
 * Er wordt enkel ingevuld wat leeg is of eerder automatisch ingevuld werd (games.auto_fields);
 * wat iemand zelf aanduidde, blijft staan. De ruwe gegevens staan in games.metadata.
 */
class GameMetadata
{
    /** Categorie → kleur en de Steam-tags/genres die erbij horen (kleine letters). */
    public const CATEGORY_MAP = [
        'Party'       => ['#ff3fb4', ['party game', 'party', 'board game', 'trivia', 'minigames']],
        'Racing'      => ['#f27724', ['racing', 'driving', 'combat racing', 'arcade racing', 'kart racing', 'motorbike', 'offroad']],
        'Sport'       => ['#3ddc84', ['sports', 'football (soccer)', 'soccer', 'basketball', 'golf', 'mini golf', 'skateboarding', 'tennis', 'baseball', 'bowling', 'hockey', 'volleyball', 'football (american)']],
        'Vechtgames'  => ['#ef4444', ['fighting', '2d fighter', '3d fighter', "beat 'em up", 'martial arts', 'wrestling', 'boxing', 'brawler']],
        'Shooter'     => ['#94a3b8', ['fps', 'shooter', 'third-person shooter', 'top-down shooter', 'arena shooter', "shoot 'em up", 'twin stick shooter', 'hero shooter', 'bullet hell']],
        'Platformer'  => ['#5b8de0', ['platformer', '2d platformer', '3d platformer', 'precision platformer', 'puzzle platformer']],
        'Puzzel'      => ['#facc15', ['puzzle', 'logic', 'physics']],
        'Muziek'      => ['#a78bfa', ['rhythm', 'music']],
        'Familie'     => ['#22d3ee', ['family friendly', 'cute', 'funny']],
        'Horror'      => ['#7f1d1d', ['horror', 'survival horror', 'psychological horror', 'zombies']],
        'Strategie'   => ['#0ea5e9', ['strategy', 'rts', 'tower defense', 'turn-based strategy', 'card game']],
        'Simulatie'   => ['#14b8a6', ['simulation', 'building', 'management', 'sandbox']],
        'Avontuur'    => ['#84cc16', ['adventure', 'open world', 'exploration', 'rpg', 'action rpg', 'action-adventure']],
        'Retro'       => ['#f59e0b', ['retro', 'pixel graphics', 'old school', 'arcade']],
        'Co-op'       => ['#3e6bb1', ['local co-op', 'couch co-op']],
    ];

    /** Steam-categorieën (id's zijn vast, de omschrijving is taalafhankelijk). */
    private const STEAM_SHARED_SCREEN = [24, 37, 39];   // Shared/Split Screen (PvP / Co-op)
    private const STEAM_LOCAL_COOP = [39];
    private const STEAM_NETWORK = [36, 38, 47, 48];     // Online PvP/Co-op, LAN PvP/Co-op
    private const STEAM_MULTI = [1, 9, 49];             // Multi-player, Co-op, PvP
    private const STEAM_SINGLE = [2];

    private const MAX_CATEGORIES = 3;
    private const UA = 'PlayDrive/1.0 (+https://playdrive.be; gamecatalogus)';

    /** Fouten per bron bij het laatste ophalen (komt mee in games.metadata.errors). */
    private array $errors = [];

    private ?string $igdbToken = null;

    /** Gegevens ophalen en invullen. → ['status' => ok|not_found|error|skipped, 'changed' => bool] */
    public function enrich(Game $game): array
    {
        if ($game->source !== 'steam' || ! ctype_digit((string) $game->external_id)) {
            $game->forceFill(['metadata_status' => 'skipped', 'metadata_fetched_at' => now()])->save();

            return ['status' => 'skipped', 'changed' => false];
        }

        $this->errors = [];
        $appId = (string) $game->external_id;
        $steam = $this->steam($appId);
        $igdb = $this->igdb($appId);
        $pcgw = $this->pcgw($appId);
        $tags = $this->steamSpyTags($appId);

        $found = is_array($steam) || is_array($pcgw) || is_array($igdb) || $tags;
        $meta = [
            'steam'  => is_array($steam) ? $steam : null,
            'igdb'   => is_array($igdb) ? $igdb : null,
            'pcgw'   => is_array($pcgw) ? $pcgw : null,
            'tags'   => array_slice($tags, 0, 20, true),
            'errors' => $this->errors,
        ];

        $changed = $found ? $this->apply($game, $meta) : false;

        $game->forceFill([
            'metadata'            => $meta,
            'metadata_status'     => $found ? 'ok' : ($this->errors ? 'error' : 'not_found'),
            'metadata_fetched_at' => now(),
        ])->save();

        return ['status' => $game->metadata_status, 'changed' => $changed];
    }

    /**
     * Velden invullen. Een veld wordt enkel overschreven als het leeg is of eerder automatisch
     * ingevuld werd (games.auto_fields); wat iemand zelf aanduidde, blijft altijd staan.
     */
    public function apply(Game $game, array $meta): bool
    {
        $auto = is_array($game->auto_fields) ? $game->auto_fields : [];
        $mayWrite = fn (string $field, $current) => $current === null || array_key_exists($field, $auto);
        $fill = [];

        // Eerder automatisch ingevuld en nu geen gegevens meer: terug leegmaken (beter leeg dan fout).
        $set = function (string $field, $value, ?string $source) use ($game, $mayWrite, &$fill, &$auto) {
            if ($value !== null && $mayWrite($field, $game->{$field})) {
                $fill[$field] = $value;
                $auto[$field] = $source;
            } elseif ($value === null && array_key_exists($field, $auto)) {
                $fill[$field] = null;
                unset($auto[$field]);
            }
        };

        [$players, $playersSource] = $this->localPlayers($meta);
        $set('local_players_max', $players, $playersSource);

        [$lan, $lanSource] = $this->network($meta);
        $set('lan_multiplayer', $lan, $lanSource);

        $changed = false;
        DB::transaction(function () use ($game, $meta, &$fill, &$auto, &$changed) {
            $hasCategories = $game->categories()->exists();
            if (! $hasCategories || array_key_exists('categories', $auto)) {
                $names = $this->categoryNames($meta);
                if ($names) {
                    $ids = $this->ensureCategories((int) $game->tenant_id, $names);
                    $before = $game->categories()->pluck('game_categories.id')->sort()->values()->all();
                    $game->categories()->sync($ids);
                    $auto['categories'] = $meta['tags'] ? 'tags' : 'steam';
                    $changed = $changed || $before !== collect($ids)->sort()->values()->all();
                }
            }

            foreach ($fill as $field => $value) {
                if ($game->{$field} !== $value) {
                    $changed = true;
                }
            }

            $game->forceFill([...$fill, 'auto_fields' => $auto ?: null])->save();
        });

        return $changed;
    }

    // ── Bronnen ─────────────────────────────────────────────────────────

    /** Steam Store: genres + categorieën. null = niet gevonden, false = fout. */
    private function steam(string $appId): array|false|null
    {
        try {
            $res = Http::withHeaders(['User-Agent' => self::UA])->timeout(10)->retry(2, 1500, throw: false)
                ->get('https://store.steampowered.com/api/appdetails', ['appids' => $appId, 'l' => 'english', 'cc' => 'be']);
            if (! $res->successful()) {
                $this->errors['steam'] = 'HTTP '.$res->status();
                return false;
            }
            $entry = $res->json($appId);
            if (! ($entry['success'] ?? false)) {
                return null;
            }
            $data = $entry['data'] ?? [];

            return [
                'type'       => $data['type'] ?? null,
                'genres'     => collect($data['genres'] ?? [])->map(fn ($g) => ['id' => (int) $g['id'], 'name' => (string) ($g['description'] ?? '')])->values()->all(),
                'categories' => collect($data['categories'] ?? [])->map(fn ($c) => ['id' => (int) $c['id'], 'name' => (string) ($c['description'] ?? '')])->values()->all(),
            ];
        } catch (Throwable $e) {
            $this->errors['steam'] = mb_substr($e->getMessage(), 0, 160);
            return false;
        }
    }

    /** PCGamingWiki (Cargo): lokaal / LAN / online en het aantal spelers. */
    private function pcgw(string $appId): array|false|null
    {
        try {
            $res = Http::withHeaders(['User-Agent' => self::UA])->timeout(10)->retry(2, 1500, throw: false)
                ->get('https://www.pcgamingwiki.com/w/api.php', [
                    'action'  => 'cargoquery',
                    'format'  => 'json',
                    'tables'  => 'Infobox_game,Multiplayer',
                    'join_on' => 'Infobox_game._pageID=Multiplayer._pageID',
                    'fields'  => 'Infobox_game._pageName=page,Multiplayer.Local=local,Multiplayer.Local_players=local_players,'
                        .'Multiplayer.LAN=lan,Multiplayer.LAN_players=lan_players,Multiplayer.Online=online,Multiplayer.Online_players=online_players',
                    'where'   => 'Infobox_game.Steam_AppID HOLDS "'.$appId.'"',
                    'limit'   => 1,
                ]);
            if (! $res->successful()) {
                $this->errors['pcgw'] = 'HTTP '.$res->status();
                return false;
            }
            if ($res->json('error')) {
                $this->errors['pcgw'] = mb_substr((string) ($res->json('error.info') ?? 'Cargo-fout'), 0, 160);
                return false;
            }
            $row = $res->json('cargoquery.0.title');
            if (! is_array($row)) {
                return null;
            }

            // Cargo zet "_" in aliassen om naar spaties.
            $norm = [];
            foreach ($row as $key => $value) {
                $norm[str_replace(' ', '_', strtolower((string) $key))] = is_string($value) ? trim($value) : $value;
            }

            return [
                'page'           => $norm['page'] ?? null,
                'local'          => $norm['local'] ?? null,
                'local_players'  => $this->int($norm['local_players'] ?? null),
                'lan'            => $norm['lan'] ?? null,
                'lan_players'    => $this->int($norm['lan_players'] ?? null),
                'online'         => $norm['online'] ?? null,
                'online_players' => $this->int($norm['online_players'] ?? null),
            ];
        } catch (Throwable $e) {
            $this->errors['pcgw'] = mb_substr($e->getMessage(), 0, 160);
            return false;
        }
    }

    /**
     * IGDB (Twitch): multiplayer-modi met het maximum aantal spelers offline (zelfde scherm/toestel),
     * online en LAN. Enkel als IGDB_CLIENT_ID/IGDB_CLIENT_SECRET ingesteld zijn.
     */
    private function igdb(string $appId): array|false|null
    {
        $id = config('services.igdb.client_id');
        $secret = config('services.igdb.client_secret');
        if (! $id || ! $secret) {
            return null;
        }

        try {
            $token = $this->igdbToken ??= Cache::remember('igdb-token', now()->addDays(30), function () use ($id, $secret) {
                $res = Http::asForm()->timeout(10)->post('https://id.twitch.tv/oauth2/token', [
                    'client_id' => $id, 'client_secret' => $secret, 'grant_type' => 'client_credentials',
                ]);
                if (! $res->successful() || ! $res->json('access_token')) {
                    throw new \RuntimeException('Twitch-token: HTTP '.$res->status());
                }

                return (string) $res->json('access_token');
            });

            $call = fn (string $endpoint, string $body) => Http::withHeaders(['Client-ID' => $id, 'Authorization' => 'Bearer '.$token])
                ->withBody($body, 'text/plain')->timeout(10)->retry(2, 1000, throw: false)
                ->post("https://api.igdb.com/v4/{$endpoint}");

            // Steam-ID → IGDB-game (bron 1 = Steam; oudere velden heetten "category").
            $res = $call('external_games', 'fields game, uid, category, external_game_source; where uid = "'.$appId.'"; limit 20;');
            if ($res->status() === 401) {
                Cache::forget('igdb-token');
            }
            if (! $res->successful()) {
                $this->errors['igdb'] = 'HTTP '.$res->status();
                return false;
            }
            $match = collect($res->json() ?? [])->first(fn ($row) => (int) ($row['external_game_source'] ?? $row['category'] ?? 0) === 1);
            if (! $match || empty($match['game'])) {
                return null;
            }

            $res = $call('multiplayer_modes', 'fields platform, offlinemax, offlinecoopmax, onlinemax, onlinecoopmax, lancoop, splitscreen, offlinecoop, onlinecoop; where game = '.(int) $match['game'].'; limit 50;');
            if (! $res->successful()) {
                $this->errors['igdb'] = 'HTTP '.$res->status();
                return false;
            }
            $modes = collect($res->json() ?? []);
            // Pc (platform 6) als die er is, anders alle platformen.
            $pc = $modes->where('platform', 6);
            $use = $pc->isNotEmpty() ? $pc : $modes;

            return [
                'game'       => (int) $match['game'],
                'offlinemax' => (int) $use->max(fn ($m) => max((int) ($m['offlinemax'] ?? 0), (int) ($m['offlinecoopmax'] ?? 0))) ?: null,
                'onlinemax'  => (int) $use->max(fn ($m) => max((int) ($m['onlinemax'] ?? 0), (int) ($m['onlinecoopmax'] ?? 0))) ?: null,
                'lan'        => $use->contains(fn ($m) => ! empty($m['lancoop'])),
                'splitscreen'=> $use->contains(fn ($m) => ! empty($m['splitscreen'])),
                'modes'      => $use->count(),
            ];
        } catch (Throwable $e) {
            $this->errors['igdb'] = mb_substr($e->getMessage(), 0, 160);
            return false;
        }
    }

    /** SteamSpy: Steam user tags met aantal stemmen, hoogste eerst. */
    private function steamSpyTags(string $appId): array
    {
        try {
            $res = Http::withHeaders(['User-Agent' => self::UA])->timeout(10)->retry(1, 1500, throw: false)
                ->get('https://steamspy.com/api.php', ['request' => 'appdetails', 'appid' => $appId]);
            if (! $res->successful()) {
                $this->errors['steamspy'] = 'HTTP '.$res->status();
            }
            $tags = $res->successful() ? $res->json('tags') : null;
            if (! is_array($tags) || ! $tags) {
                return [];
            }
            arsort($tags);

            return array_map('intval', $tags);
        } catch (Throwable $e) {
            $this->errors['steamspy'] = mb_substr($e->getMessage(), 0, 160);
            return [];
        }
    }

    // ── Afleiden ────────────────────────────────────────────────────────

    /**
     * Spelers op één scherm → [aantal, bron]. Volgorde: IGDB, PCGamingWiki, Steam (gok).
     * Afgerond naar beneden op de keuzes in de backoffice (1/2/4/8).
     */
    private function localPlayers(array $meta): array
    {
        $igdb = $meta['igdb'] ?? null;
        $pcgw = $meta['pcgw'] ?? null;
        $steamCats = $this->steamCategoryIds($meta);

        [$n, $source] = [null, null];

        if (is_array($igdb) && ($igdb['offlinemax'] ?? 0) > 0) {
            [$n, $source] = [(int) $igdb['offlinemax'], 'igdb'];
        } elseif (is_array($pcgw) && in_array($pcgw['local'] ?? null, ['true', 'limited'], true) && ! empty($pcgw['local_players'])) {
            [$n, $source] = [(int) $pcgw['local_players'], 'pcgw'];
        } elseif (is_array($pcgw) && ($pcgw['local'] ?? null) === 'false') {
            [$n, $source] = [1, 'pcgw'];
        } elseif ($steamCats && array_intersect($steamCats, self::STEAM_SHARED_SCREEN)) {
            [$n, $source] = [2, 'steam'];             // Steam zegt niet hoeveel: minstens 2 (gok)
        } elseif (is_array($pcgw) && ($pcgw['local'] ?? null) === 'true') {
            [$n, $source] = [2, 'steam'];
        } elseif ($steamCats && array_intersect($steamCats, self::STEAM_SINGLE) && ! array_intersect($steamCats, [...self::STEAM_MULTI, ...self::STEAM_NETWORK, 44])) {
            [$n, $source] = [1, 'steam'];             // enkel single-player
        }

        if ($n === null) {
            return [null, null];
        }

        foreach (array_reverse(Game::LOCAL_PLAYER_OPTIONS) as $option) {   // 8, 4, 2, 1
            if ($n >= $option) {
                return [$option, $source];
            }
        }

        return [1, $source];
    }

    /** Samen spelen over LAN/online → [ja/nee, bron]. */
    private function network(array $meta): array
    {
        $igdb = $meta['igdb'] ?? null;
        $pcgw = $meta['pcgw'] ?? null;
        $steamCats = $this->steamCategoryIds($meta);

        if (is_array($igdb) && ($igdb['lan'] || ($igdb['onlinemax'] ?? 0) > 1)) {
            return [true, 'igdb'];
        }
        if (is_array($pcgw) && (($pcgw['lan'] ?? null) === 'true' || ($pcgw['online'] ?? null) === 'true')) {
            return [true, 'pcgw'];
        }
        if ($steamCats && array_intersect($steamCats, self::STEAM_NETWORK)) {
            return [true, 'steam'];
        }
        if (is_array($pcgw) && ($pcgw['lan'] ?? null) === 'false' && ($pcgw['online'] ?? null) === 'false') {
            return [false, 'pcgw'];
        }
        if ($steamCats && ! array_intersect($steamCats, [...self::STEAM_MULTI, ...self::STEAM_NETWORK])) {
            return [false, 'steam'];
        }

        return [null, null];
    }

    /** Tot 3 categorieën uit de meest gestemde tags (reserve: Steam-genres), plus Co-op bij lokale co-op. */
    public function categoryNames(array $meta): array
    {
        $names = [];
        $add = function (string $text) use (&$names) {
            $text = mb_strtolower(trim($text));
            foreach (self::CATEGORY_MAP as $name => [, $needles]) {
                if (count($names) < self::MAX_CATEGORIES && in_array($text, $needles, true) && ! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        };

        foreach (array_slice(array_keys($meta['tags'] ?? []), 0, 12) as $tag) {
            $add((string) $tag);
        }

        if (! $names) {
            foreach (($meta['steam']['genres'] ?? []) as $genre) {
                $add((string) $genre['name']);
            }
        }

        $steamCats = $this->steamCategoryIds($meta);
        $pcgwLocal = ($meta['pcgw']['local'] ?? null) === 'true';
        if (! in_array('Co-op', $names, true)
            && (array_intersect($steamCats, self::STEAM_LOCAL_COOP) || ($pcgwLocal && array_intersect($steamCats, [9])))) {
            $names[] = 'Co-op';                               // mag een 4de categorie zijn
        }

        return array_values(array_unique($names));
    }

    /** Categorieën van de venue opzoeken (hoofdletters negeren) of aanmaken. */
    private function ensureCategories(int $tenantId, array $names): array
    {
        $existing = GameCategory::query()->where('tenant_id', $tenantId)->get()
            ->keyBy(fn (GameCategory $c) => mb_strtolower(trim($c->name)));
        $sort = (int) GameCategory::query()->where('tenant_id', $tenantId)->max('sort_order');

        $ids = [];
        foreach ($names as $name) {
            $category = $existing->get(mb_strtolower($name));
            if (! $category) {
                $category = GameCategory::query()->create([
                    'tenant_id'  => $tenantId,
                    'name'       => $name,
                    'color'      => self::CATEGORY_MAP[$name][0] ?? '#5b8de0',
                    'sort_order' => ++$sort,
                ]);
                $existing->put(mb_strtolower($name), $category);
            }
            $ids[] = $category->id;
        }

        return $ids;
    }

    private function steamCategoryIds(array $meta): array
    {
        return collect($meta['steam']['categories'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        // "4", "2-4", "4+" → hoogste getal
        preg_match_all('/\d+/', (string) $value, $m);

        return $m[0] ? (int) max($m[0]) : null;
    }
}

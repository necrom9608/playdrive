<?php

namespace App\Domain\Games;

use App\Models\Game;
use App\Models\GameCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gegevens van Steam-games automatisch ophalen en invullen:
 *
 *   PCGamingWiki  aantal spelers op één scherm (Local_players), LAN en online   → spelers
 *   SteamSpy      user tags van Steam (Racing, Party Game, Fighting, …)          → categorieën
 *   Steam Store   genres + categorieën (Shared/Split Screen, LAN PvP, …)         → reserve als de rest ontbreekt
 *
 * Er wordt enkel ingevuld wat nog leeg is: een game met al categorieën of een aantal spelers
 * houdt die. De ruwe gegevens worden bewaard in games.metadata.
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

    /** Gegevens ophalen en invullen. → ['status' => ok|not_found|error|skipped, 'changed' => bool] */
    public function enrich(Game $game): array
    {
        if ($game->source !== 'steam' || ! ctype_digit((string) $game->external_id)) {
            $game->forceFill(['metadata_status' => 'skipped', 'metadata_fetched_at' => now()])->save();

            return ['status' => 'skipped', 'changed' => false];
        }

        $appId = (string) $game->external_id;
        $steam = $this->steam($appId);
        $pcgw = $this->pcgw($appId);
        $tags = $this->steamSpyTags($appId);

        $found = is_array($steam) || is_array($pcgw) || $tags;
        $meta = [
            'steam' => is_array($steam) ? $steam : null,
            'pcgw'  => is_array($pcgw) ? $pcgw : null,
            'tags'  => array_slice($tags, 0, 20, true),
        ];

        $changed = $found ? $this->apply($game, $meta) : false;

        $game->forceFill([
            'metadata'            => $meta,
            'metadata_status'     => $found ? 'ok' : (($steam === false || $pcgw === false) ? 'error' : 'not_found'),
            'metadata_fetched_at' => now(),
        ])->save();

        return ['status' => $game->metadata_status, 'changed' => $changed];
    }

    /** Lege velden invullen op basis van de opgehaalde gegevens. */
    public function apply(Game $game, array $meta): bool
    {
        $changed = false;
        $fill = [];

        if ($game->local_players_max === null && ($players = $this->localPlayers($meta)) !== null) {
            $fill['local_players_max'] = $players;
        }
        if ($game->lan_multiplayer === null && ($lan = $this->network($meta)) !== null) {
            $fill['lan_multiplayer'] = $lan;
        }

        DB::transaction(function () use ($game, $meta, $fill, &$changed) {
            if ($fill) {
                $game->forceFill($fill)->save();
                $changed = true;
            }

            if (! $game->categories()->exists()) {
                $names = $this->categoryNames($meta);
                if ($names) {
                    $ids = $this->ensureCategories((int) $game->tenant_id, $names);
                    $game->categories()->sync($ids);
                    $changed = true;
                }
            }
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
        } catch (Throwable) {
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
        } catch (Throwable) {
            return false;
        }
    }

    /** SteamSpy: Steam user tags met aantal stemmen, hoogste eerst. */
    private function steamSpyTags(string $appId): array
    {
        try {
            $res = Http::withHeaders(['User-Agent' => self::UA])->timeout(10)->retry(1, 1500, throw: false)
                ->get('https://steamspy.com/api.php', ['request' => 'appdetails', 'appid' => $appId]);
            $tags = $res->successful() ? $res->json('tags') : null;
            if (! is_array($tags) || ! $tags) {
                return [];
            }
            arsort($tags);

            return array_map('intval', $tags);
        } catch (Throwable) {
            return [];
        }
    }

    // ── Afleiden ────────────────────────────────────────────────────────

    /** Spelers op één scherm, afgerond naar beneden op de keuzes in de backoffice (1/2/4/8). */
    private function localPlayers(array $meta): ?int
    {
        $pcgw = $meta['pcgw'] ?? null;
        $steamCats = $this->steamCategoryIds($meta);

        $n = null;
        if (is_array($pcgw)) {
            if (($pcgw['local'] ?? null) === 'true' && $pcgw['local_players']) {
                $n = $pcgw['local_players'];
            } elseif (($pcgw['local'] ?? null) === 'true') {
                $n = 2;
            } elseif (($pcgw['local'] ?? null) === 'false') {
                $n = 1;
            }
        }

        if ($n === null && $steamCats) {
            if (array_intersect($steamCats, self::STEAM_SHARED_SCREEN)) {
                $n = 2;                                       // Steam zegt niet hoeveel: minstens 2
            } elseif (array_intersect($steamCats, self::STEAM_SINGLE)) {
                $n = 1;
            }
        }

        if ($n === null) {
            return null;
        }

        $options = array_reverse(Game::LOCAL_PLAYER_OPTIONS);  // 8, 4, 2, 1
        foreach ($options as $option) {
            if ($n >= $option) {
                return $option;
            }
        }

        return 1;
    }

    /** Samen spelen over LAN/online? */
    private function network(array $meta): ?bool
    {
        $pcgw = $meta['pcgw'] ?? null;
        if (is_array($pcgw) && (($pcgw['lan'] ?? null) === 'true' || ($pcgw['online'] ?? null) === 'true')) {
            return true;
        }

        $steamCats = $this->steamCategoryIds($meta);
        if ($steamCats && array_intersect($steamCats, self::STEAM_NETWORK)) {
            return true;
        }

        if (is_array($pcgw) && ($pcgw['lan'] ?? null) === 'false' && ($pcgw['online'] ?? null) === 'false') {
            return false;
        }

        if ($steamCats && ! array_intersect($steamCats, [...self::STEAM_MULTI, ...self::STEAM_NETWORK])) {
            return false;                                     // geen enkele multiplayer-categorie
        }

        return null;
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

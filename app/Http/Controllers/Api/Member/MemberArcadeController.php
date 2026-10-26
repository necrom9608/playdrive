<?php

namespace App\Http\Controllers\Api\Member;

use App\Domain\Arcade\ArcadeGames;
use App\Http\Controllers\Controller;
use App\Models\ArcadeCard;
use App\Models\ArcadePlay;
use App\Models\ArcadePlayer;
use App\Models\ArcadeScore;
use App\Models\ArcadeSong;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arcade-scores (Asphyxia) in de member-app. Alles per venue (?venue=slug).
 *
 * Een account kan meerdere Asphyxia-spelers hebben (bv. twee kaarten met een eigen profiel).
 * In rankings tonen we enkel de naam die de speler in de game koos, nooit de echte naam.
 */
class MemberArcadeController extends Controller
{
    /** GET /arcade — gekoppelde spelers, stats-overzicht en recente plays. */
    public function summary(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $players = $this->players($request, $tenant);
        $ids = $players->pluck('id');

        $scores = $ids->isEmpty() ? collect() : ArcadeScore::query()->whereIn('arcade_player_id', $ids)->get();
        $plays = $ids->isEmpty() ? collect() : ArcadePlay::query()
            ->whereIn('arcade_player_id', $ids)
            ->get(['id', 'game', 'song_id', 'chart', 'plays', 'is_new_best', 'played_at']);

        // speelbeurten per game: speelteller van Asphyxia waar die bestaat, anders de historiek
        $perGame = [];
        foreach (ArcadeGames::GAMES as $game => $meta) {
            $gScores = $scores->where('game', $game);
            $gPlays = $plays->where('game', $game);
            if ($gScores->isEmpty() && $gPlays->isEmpty()) {
                continue;
            }
            $count = in_array($game, ArcadeGames::COUNTED, true)
                ? max((int) $gScores->sum('play_count'), (int) $gPlays->sum('plays'))
                : max((int) $gPlays->sum('plays'), $gScores->count());
            $perGame[] = [
                'game'           => $game,
                'name'           => $meta['name'],
                'short'          => $meta['short'],
                'plays'          => $count,
                'charts'         => $gScores->count(),
                'last_played_at' => $gScores->max('last_played_at')?->toIso8601String(),
            ];
        }
        usort($perGame, fn ($a, $b) => $b['plays'] <=> $a['plays']);

        // favoriete songs
        $songPlays = [];
        foreach ($scores as $s) {
            $k = "{$s->game}|{$s->song_id}";
            $songPlays[$k] = ($songPlays[$k] ?? 0) + (in_array($s->game, ArcadeGames::COUNTED, true) ? (int) $s->play_count : 0);
        }
        foreach ($plays as $p) {
            if (in_array($p->game, ArcadeGames::COUNTED, true)) {
                continue;
            }
            $k = "{$p->game}|{$p->song_id}";
            $songPlays[$k] = ($songPlays[$k] ?? 0) + (int) $p->plays;
        }
        arsort($songPlays);
        $favKeys = array_slice(array_keys(array_filter($songPlays)), 0, 5);
        $titles = $this->titles(collect($favKeys)->map(fn ($k) => explode('|', $k)));
        $favorites = array_map(function ($k) use ($songPlays, $titles) {
            [$game, $songId] = explode('|', $k);

            return [
                'game'    => $game,
                'short'   => ArcadeGames::short($game),
                'song_id' => (int) $songId,
                'title'   => $titles[$k]['title'] ?? "Song #$songId",
                'artist'  => $titles[$k]['artist'] ?? null,
                'plays'   => $songPlays[$k],
            ];
        }, $favKeys);

        $recent = $ids->isEmpty() ? collect() : ArcadePlay::query()
            ->whereIn('arcade_player_id', $ids)
            ->orderByDesc('played_at')->orderByDesc('id')
            ->limit(10)->get();

        return response()->json([
            'data' => [
                'venue'   => ['slug' => $tenant->slug, 'name' => $tenant->display_name],
                'games'   => ArcadeGames::all(),
                'linked'  => $players->isNotEmpty(),
                'players' => $players->map(fn (ArcadePlayer $p) => [
                    'id'             => $p->id,
                    'name'           => $p->displayName(),
                    'games'          => collect($p->games ?? [])->map(fn ($g, $key) => [
                        'game' => $key, 'short' => ArcadeGames::short($key), 'name' => $g['name'] ?? null, 'id' => $g['id'] ?? null,
                    ])->values(),
                    'cards'          => $p->cards->map(fn ($c) => ['print' => $c->print, 'cid' => $c->cid])->values(),
                    'linked_via'     => $p->linked_via,
                    'last_played_at' => $p->last_played_at?->toIso8601String(),
                ])->values(),
                'stats'   => [
                    'total_plays'    => array_sum(array_column($perGame, 'plays')),
                    'charts_played'  => $scores->count(),
                    'records_30d'    => $plays->where('is_new_best', true)->where('played_at', '>=', now()->subDays(30))->count(),
                    'first_places'   => $this->firstPlaces($tenant->id, $ids),
                    'last_played_at' => $players->max('last_played_at')?->toIso8601String(),
                    'per_game'       => $perGame,
                    'favorites'      => $favorites,
                ],
                'recent'  => $this->transformPlays($recent),
            ],
        ]);
    }

    /** GET /arcade/scores?game=&sort=recent|score|title|rank — persoonlijke bests. */
    public function scores(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $ids = $this->players($request, $tenant)->pluck('id');
        $game = $this->game($request);

        $rows = $ids->isEmpty() ? collect() : ArcadeScore::query()
            ->whereIn('arcade_player_id', $ids)
            ->when($game, fn ($q) => $q->where('game', $game))
            ->select('arcade_scores.*')
            ->selectSub(function ($q) {
                $q->from('arcade_scores as o')
                    ->selectRaw('count(*) + 1')
                    ->whereColumn('o.tenant_id', 'arcade_scores.tenant_id')
                    ->whereColumn('o.game', 'arcade_scores.game')
                    ->whereColumn('o.song_id', 'arcade_scores.song_id')
                    ->whereColumn('o.chart', 'arcade_scores.chart')
                    ->whereColumn('o.score', '>', 'arcade_scores.score');
            }, 'position')
            ->get();

        $titles = $this->titles($rows->map(fn ($s) => [$s->game, $s->song_id]));
        $data = $rows->map(fn (ArcadeScore $s) => [
            'id'             => $s->id,
            'game'           => $s->game,
            'short'          => ArcadeGames::short($s->game),
            'song_id'        => $s->song_id,
            'title'          => $titles["{$s->game}|{$s->song_id}"]['title'] ?? "Song #{$s->song_id}",
            'artist'         => $titles["{$s->game}|{$s->song_id}"]['artist'] ?? null,
            'level'          => $titles["{$s->game}|{$s->song_id}"]['levels'][$s->chart] ?? null,
            'chart'          => $s->chart,
            'chart_order'    => $s->chart_order,
            'score'          => $s->score,
            'grade_label'    => $s->grade_label,
            'clear_label'    => $s->clear_label,
            'combo'          => $s->combo,
            'play_count'     => $s->play_count,
            'position'       => $s->score === null ? null : (int) $s->position,
            'achieved_at'    => $s->achieved_at?->toIso8601String(),
            'last_played_at' => $s->last_played_at?->toIso8601String(),
        ]);

        $sort = (string) $request->query('sort', 'recent');
        $data = match ($sort) {
            'score' => $data->sortByDesc('score'),
            'title' => $data->sortBy([['title', 'asc'], ['chart_order', 'asc']]),
            'rank'  => $data->sortBy([['position', 'asc'], ['score', 'desc']]),
            default => $data->sortByDesc('last_played_at'),
        };

        return response()->json(['data' => $data->values()]);
    }

    /** GET /arcade/plays?game=&page= — speelhistoriek. */
    public function plays(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $ids = $this->players($request, $tenant)->pluck('id');
        $game = $this->game($request);

        $page = ArcadePlay::query()
            ->whereIn('arcade_player_id', $ids->all() ?: [0])
            ->when($game, fn ($q) => $q->where('game', $game))
            ->orderByDesc('played_at')->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => $this->transformPlays(collect($page->items())),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * GET /arcade/ranking?game=&song_id=&chart=
     *  - met song_id + chart: top 20 van die chart bij de venue + eigen plaats
     *  - zonder: de meest gespeelde charts bij de venue (om te kiezen)
     */
    public function ranking(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $myIds = $this->players($request, $tenant)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $game = $this->game($request);
        $songId = $request->query('song_id');
        $chart = (string) $request->query('chart', '');

        if ($game && is_numeric($songId) && $chart !== '') {
            $base = ArcadeScore::query()
                ->where('tenant_id', $tenant->id)
                ->where('game', $game)->where('song_id', (int) $songId)->where('chart', $chart)
                ->whereNotNull('score');

            $top = (clone $base)->with('player')->orderByDesc('score')->orderBy('achieved_at')->limit(20)->get();
            $mine = $myIds ? (clone $base)->whereIn('arcade_player_id', $myIds)->orderByDesc('score')->first() : null;
            $myPosition = $mine ? (clone $base)->where('score', '>', $mine->score)->count() + 1 : null;
            $song = $this->titles(collect([[$game, (int) $songId]]))["$game|$songId"] ?? null;

            return response()->json([
                'data' => [
                    'game'        => $game,
                    'short'       => ArcadeGames::short($game),
                    'song_id'     => (int) $songId,
                    'title'       => $song['title'] ?? "Song #$songId",
                    'artist'      => $song['artist'] ?? null,
                    'chart'       => $chart,
                    'level'       => $song['levels'][$chart] ?? null,
                    'total'       => (clone $base)->count(),
                    'my_position' => $myPosition,
                    'my_score'    => $mine?->score,
                    'entries'     => $top->values()->map(fn (ArcadeScore $s, $i) => [
                        'position'    => $i + 1,
                        'name'        => $s->player?->displayName($game) ?? 'Speler',
                        'score'       => $s->score,
                        'grade_label' => $s->grade_label,
                        'clear_label' => $s->clear_label,
                        'is_me'       => in_array((int) $s->arcade_player_id, $myIds, true),
                        'achieved_at' => $s->achieved_at?->toIso8601String(),
                    ]),
                ],
            ]);
        }

        // meest gespeelde charts bij de venue
        $charts = ArcadeScore::query()
            ->where('tenant_id', $tenant->id)
            ->when($game, fn ($q) => $q->where('game', $game))
            ->whereNotNull('score')
            ->groupBy('game', 'song_id', 'chart', 'chart_order')
            ->select('game', 'song_id', 'chart', 'chart_order')
            ->selectRaw('count(*) as players, max(score) as top_score, max(coalesce(last_played_at, achieved_at)) as last_at')
            ->orderByDesc('players')->orderByDesc('last_at')
            ->limit(30)
            ->get();

        $titles = $this->titles($charts->map(fn ($c) => [$c->game, $c->song_id]));
        $mineByChart = $myIds ? ArcadeScore::query()
            ->whereIn('arcade_player_id', $myIds)
            ->get(['game', 'song_id', 'chart', 'score'])
            ->groupBy(fn ($s) => "{$s->game}|{$s->song_id}|{$s->chart}") : collect();

        return response()->json([
            'data' => $charts->map(function ($c) use ($titles, $mineByChart) {
                $k = "{$c->game}|{$c->song_id}";
                $mine = $mineByChart->get("$k|{$c->chart}")?->max('score');

                return [
                    'game'      => $c->game,
                    'short'     => ArcadeGames::short($c->game),
                    'song_id'   => (int) $c->song_id,
                    'title'     => $titles[$k]['title'] ?? "Song #{$c->song_id}",
                    'chart'     => $c->chart,
                    'level'     => $titles[$k]['levels'][$c->chart] ?? null,
                    'players'   => (int) $c->players,
                    'top_score' => (int) $c->top_score,
                    'my_score'  => $mine,
                    'is_top'    => $mine !== null && (int) $mine >= (int) $c->top_score,
                ];
            })->values(),
        ]);
    }

    /**
     * POST /arcade/link {venue, code, pin} — speler koppelen met kaartnummer of in-game ID + PIN.
     * code = kaartnummer (zoals in Asphyxia), kaart-id (E004…) of de speler-ID uit een game
     * (DANCERUSH-ID, DDR-code, SDVX-ID, pop'n friend-ID).
     */
    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'pin'  => ['required', 'digits:4'],
        ]);
        $tenant = $this->tenant($request);
        $code = strtoupper((string) preg_replace('/[\s\-]/', '', $data['code']));

        $player = $this->findPlayerByCode($tenant->id, $code);

        if (! $player || ! $player->pin_hash || ! hash_equals($player->pin_hash, hash('sha256', "{$player->refid}:{$data['pin']}"))) {
            return response()->json(['message' => 'Geen speler gevonden met dit nummer en deze PIN.'], 422);
        }

        if ($player->account_id && $player->account_id !== $request->user()->id) {
            return response()->json(['message' => 'Deze speler is al gekoppeld aan een ander account. Vraag hulp aan de balie.'], 409);
        }

        $player->forceFill([
            'account_id' => $request->user()->id,
            'linked_via' => ArcadePlayer::LINK_MANUAL,
            'linked_at'  => now(),
        ])->save();

        return response()->json(['message' => 'Speler gekoppeld.', 'data' => ['id' => $player->id, 'name' => $player->displayName()]]);
    }

    /** DELETE /arcade/players/{id} — speler ontkoppelen (wordt niet opnieuw automatisch gekoppeld). */
    public function unlink(Request $request, int $id): JsonResponse
    {
        $player = ArcadePlayer::query()
            ->whereKey($id)
            ->where('account_id', $request->user()->id)
            ->firstOrFail();

        $player->forceFill(['account_id' => null, 'linked_via' => ArcadePlayer::LINK_UNLINKED, 'linked_at' => null])->save();

        return response()->json(['message' => 'Speler ontkoppeld.']);
    }

    // ------------------------------------------------------------------

    private function tenant(Request $request): Tenant
    {
        $slug = (string) $request->input('venue', $request->query('venue', ''));
        abort_if($slug === '', 422, 'Geen venue opgegeven.');

        return Tenant::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
    }

    /** @return Collection<int, ArcadePlayer> */
    private function players(Request $request, Tenant $tenant): Collection
    {
        return ArcadePlayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('account_id', $request->user()->id)
            ->with('cards')
            ->orderByDesc('last_played_at')
            ->get();
    }

    private function game(Request $request): ?string
    {
        $game = (string) $request->query('game', '');

        return ArcadeGames::exists($game) ? $game : null;
    }

    private function findPlayerByCode(int $tenantId, string $code): ?ArcadePlayer
    {
        if ($code === '') {
            return null;
        }

        $card = ArcadeCard::query()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('print', $code)->orWhere('cid', $code))
            ->first();
        if ($card) {
            return $card->player;
        }

        // in-game ID's zitten in de JSON-kolom games: klein aantal spelers per venue, dus in PHP
        $digits = ltrim($code, '0');
        foreach (ArcadePlayer::query()->where('tenant_id', $tenantId)->whereNotNull('games')->cursor() as $player) {
            foreach ($player->games ?? [] as $info) {
                $id = strtoupper((string) preg_replace('/[\s\-]/', '', (string) ($info['id'] ?? '')));
                if ($id !== '' && ($id === $code || ($digits !== '' && ltrim($id, '0') === $digits))) {
                    return $player;
                }
            }
        }

        return null;
    }

    private function firstPlaces(int $tenantId, Collection $ids): int
    {
        if ($ids->isEmpty()) {
            return 0;
        }

        return ArcadeScore::query()
            ->whereIn('arcade_player_id', $ids)
            ->whereNotNull('score')
            ->where('score', '>', 0)
            ->whereNotExists(function ($q) use ($tenantId) {
                $q->select(DB::raw(1))
                    ->from('arcade_scores as o')
                    ->where('o.tenant_id', $tenantId)
                    ->whereColumn('o.game', 'arcade_scores.game')
                    ->whereColumn('o.song_id', 'arcade_scores.song_id')
                    ->whereColumn('o.chart', 'arcade_scores.chart')
                    ->whereColumn('o.score', '>', 'arcade_scores.score');
            })
            ->count();
    }

    /**
     * Songinfo voor [game, song_id]-paren.
     *
     * @return array<string, array{title:?string, artist:?string, levels:array}>
     */
    private function titles(Collection $pairs): array
    {
        $pairs = $pairs->filter()->unique(fn ($p) => "{$p[0]}|{$p[1]}");
        if ($pairs->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($pairs->groupBy(fn ($p) => $p[0]) as $game => $list) {
            ArcadeSong::query()
                ->where('game', $game)
                ->whereIn('song_id', $list->map(fn ($p) => (int) $p[1])->all())
                ->get(['game', 'song_id', 'title', 'artist', 'levels'])
                ->each(function ($s) use (&$out) {
                    $out["{$s->game}|{$s->song_id}"] = [
                        'title'  => $s->title,
                        'artist' => $s->artist,
                        'levels' => $s->levels ?? [],
                    ];
                });
        }

        return $out;
    }

    private function transformPlays(Collection $plays): array
    {
        $titles = $this->titles($plays->map(fn ($p) => [$p->game, $p->song_id]));

        return $plays->map(fn (ArcadePlay $p) => [
            'id'          => $p->id,
            'game'        => $p->game,
            'short'       => ArcadeGames::short($p->game),
            'song_id'     => $p->song_id,
            'title'       => $titles["{$p->game}|{$p->song_id}"]['title'] ?? "Song #{$p->song_id}",
            'chart'       => $p->chart,
            'level'       => $titles["{$p->game}|{$p->song_id}"]['levels'][$p->chart] ?? null,
            'score'       => $p->score,
            'grade_label' => $p->grade_label,
            'clear_label' => $p->clear_label,
            'combo'       => $p->combo,
            'plays'       => $p->plays,
            'is_new_best' => $p->is_new_best,
            'played_at'   => $p->played_at?->toIso8601String(),
        ])->values()->all();
    }
}

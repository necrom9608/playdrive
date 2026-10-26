<?php

namespace App\Domain\Arcade;

use App\Models\ArcadeCard;
use App\Models\ArcadePlayer;
use App\Models\ArcadeScore;
use App\Models\ArcadeSong;
use App\Models\ArcadeSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwerkt een batch van playdrive-sync (zie asphyxia-repo, playdrive-sync/playdrive_sync.py).
 *
 * Alles is idempotent: dezelfde batch twee keer sturen verandert niets.
 *  - players: upsert op (tenant, refid); kaarten = volledige lijst van die speler
 *  - songs:   upsert op (game, song_id)
 *  - scores:  beste resultaat per chart; een lagere score overschrijft nooit een hogere
 *  - plays:   uniek op (tenant, source_key)
 */
class ArcadeIngestService
{
    public function ingest(ArcadeSource $source, array $payload): array
    {
        $tenantId = (int) $source->tenant_id;
        $result = ['players' => 0, 'songs' => 0, 'scores' => 0, 'plays' => 0, 'linked' => 0];

        DB::transaction(function () use ($tenantId, $payload, &$result) {
            $result['players'] = $this->players($tenantId, (array) ($payload['players'] ?? []));
            $result['songs'] = $this->songs((array) ($payload['songs'] ?? []));
            $result['scores'] = $this->scores($tenantId, (array) ($payload['scores'] ?? []));
            $result['plays'] = $this->plays($tenantId, (array) ($payload['plays'] ?? []));
        });

        if ($result['players'] > 0 || ! empty($payload['scores']) || ! empty($payload['plays'])) {
            $result['linked'] = ArcadeCardMatcher::autoLink($tenantId);
        }

        return $result;
    }

    // ------------------------------------------------------------------

    private function players(int $tenantId, array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $refid = $this->str($row['refid'] ?? null, 32);
            if (! $refid) {
                continue;
            }

            $games = [];
            foreach ((array) ($row['games'] ?? []) as $game => $info) {
                if (! ArcadeGames::exists((string) $game)) {
                    continue;
                }
                $games[$game] = [
                    'name' => $this->str($info['name'] ?? null, 64),
                    'id'   => $this->str($info['id'] ?? null, 32),
                ];
            }

            $pinHash = $row['pin_hash'] ?? null;
            $pinHash = is_string($pinHash) && preg_match('/^[0-9a-f]{64}$/', $pinHash) ? $pinHash : null;

            $player = ArcadePlayer::query()->firstOrNew(['tenant_id' => $tenantId, 'refid' => $refid]);
            $player->fill([
                'name'     => $this->str($row['name'] ?? null, 64),
                'games'    => $games ?: null,
                'pin_hash' => $pinHash,
            ])->save();

            // kaarten: volledige lijst van deze speler
            $cids = [];
            foreach ((array) ($row['cards'] ?? []) as $card) {
                $cid = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', (string) ($card['cid'] ?? '')));
                if (strlen($cid) !== 16) {
                    continue;
                }
                $cids[] = $cid;
                ArcadeCard::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'cid' => $cid],
                    ['arcade_player_id' => $player->id, 'print' => $this->str($card['print'] ?? null, 16)]
                );
            }
            ArcadeCard::query()
                ->where('arcade_player_id', $player->id)
                ->when($cids, fn ($q) => $q->whereNotIn('cid', $cids))
                ->delete();

            $n++;
        }

        return $n;
    }

    private function songs(array $rows): int
    {
        $data = [];
        $now = now();
        foreach ($rows as $row) {
            $game = (string) ($row['game'] ?? '');
            $songId = filter_var($row['song_id'] ?? null, FILTER_VALIDATE_INT);
            if (! ArcadeGames::exists($game) || $songId === false || $songId < 0) {
                continue;
            }
            $levels = [];
            foreach ((array) ($row['levels'] ?? []) as $chart => $lv) {
                $levels[$this->str($chart, 24)] = (int) $lv;
            }
            $data[] = [
                'game'       => $game,
                'song_id'    => $songId,
                'title'      => $this->str($row['title'] ?? null, 255),
                'artist'     => $this->str($row['artist'] ?? null, 255),
                'levels'     => $levels ? json_encode($levels, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($data, 500) as $chunk) {
            ArcadeSong::query()->upsert($chunk, ['game', 'song_id'], ['title', 'artist', 'levels', 'updated_at']);
        }

        return count($data);
    }

    private function scores(int $tenantId, array $rows): int
    {
        $players = $this->playerIds($tenantId, $rows);
        $n = 0;

        foreach ($rows as $row) {
            $r = $this->chartRow($row);
            $playerId = $players[$row['refid'] ?? ''] ?? null;
            if (! $r || ! $playerId) {
                continue;
            }

            $at = $this->time($row['updated_at'] ?? null);
            $score = ArcadeScore::query()->firstOrNew([
                'arcade_player_id' => $playerId,
                'game'             => $r['game'],
                'song_id'          => $r['song_id'],
                'chart'            => $r['chart'],
            ]);
            $score->tenant_id = $tenantId;
            $score->chart_order = $r['chart_order'];

            $improved = ! $score->exists
                || ($r['score'] !== null && ($score->score === null || $r['score'] > $score->score));

            if ($improved) {
                $score->fill([
                    'score'       => $r['score'],
                    'grade'       => $r['grade'],
                    'grade_label' => $r['grade_label'],
                    'combo'       => $r['combo'],
                    'extra'       => $r['extra'],
                    'achieved_at' => $at,
                ]);
            }

            // clear-lamp: de beste houden, ook als de score niet beter was
            if ($r['clear'] !== null && ($score->clear === null || $r['clear'] > $score->clear)) {
                $score->clear = $r['clear'];
                $score->clear_label = $r['clear_label'];
            }
            if ($r['play_count'] !== null) {
                $score->play_count = $r['play_count'];
            }
            if ($at && (! $score->last_played_at || $at->gt($score->last_played_at))) {
                $score->last_played_at = $at;
            }

            if ($score->isDirty()) {
                $score->save();
                $n++;
            }
        }

        $this->touchPlayers($rows, $players, 'updated_at');

        return $n;
    }

    private function plays(int $tenantId, array $rows): int
    {
        $players = $this->playerIds($tenantId, $rows);
        $data = [];
        $now = now();

        foreach ($rows as $row) {
            $r = $this->chartRow($row);
            $playerId = $players[$row['refid'] ?? ''] ?? null;
            $key = $this->str($row['key'] ?? null, 160);
            $at = $this->time($row['played_at'] ?? null);
            if (! $r || ! $playerId || ! $key || ! $at) {
                continue;
            }
            $data[] = [
                'tenant_id'        => $tenantId,
                'arcade_player_id' => $playerId,
                'source_key'       => $key,
                'game'             => $r['game'],
                'song_id'          => $r['song_id'],
                'chart'            => $r['chart'],
                'chart_order'      => $r['chart_order'],
                'score'            => $r['score'],
                'grade'            => $r['grade'],
                'grade_label'      => $r['grade_label'],
                'clear'            => $r['clear'],
                'clear_label'      => $r['clear_label'],
                'combo'            => $r['combo'],
                'plays'            => max(1, min(1000, (int) ($row['plays'] ?? 1))),
                'is_new_best'      => (bool) ($row['new_best'] ?? false),
                'extra'            => $r['extra'] ? json_encode($r['extra']) : null,
                'played_at'        => $at,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        $inserted = 0;
        foreach (array_chunk($data, 500) as $chunk) {
            $inserted += DB::table('arcade_plays')->insertOrIgnore($chunk);
        }

        $this->touchPlayers($rows, $players, 'played_at');

        return $inserted;
    }

    // ------------------------------------------------------------------

    /** refid → arcade_player_id; onbekende refids krijgen een lege speler (profiel volgt later). */
    private function playerIds(int $tenantId, array $rows): array
    {
        $refids = collect($rows)->pluck('refid')->filter(fn ($r) => is_string($r) && $r !== '' && strlen($r) <= 32)->unique()->values();
        if ($refids->isEmpty()) {
            return [];
        }

        $map = ArcadePlayer::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('refid', $refids)
            ->pluck('id', 'refid')
            ->all();

        foreach ($refids as $refid) {
            if (! isset($map[$refid])) {
                $map[$refid] = ArcadePlayer::query()->create(['tenant_id' => $tenantId, 'refid' => $refid])->id;
            }
        }

        return $map;
    }

    private function touchPlayers(array $rows, array $players, string $field): void
    {
        $latest = [];
        foreach ($rows as $row) {
            $id = $players[$row['refid'] ?? ''] ?? null;
            $at = $this->time($row[$field] ?? null);
            if ($id && $at && (! isset($latest[$id]) || $at->gt($latest[$id]))) {
                $latest[$id] = $at;
            }
        }
        foreach ($latest as $id => $at) {
            ArcadePlayer::query()
                ->whereKey($id)
                ->where(fn ($q) => $q->whereNull('last_played_at')->orWhere('last_played_at', '<', $at))
                ->update(['last_played_at' => $at]);
        }
    }

    private function chartRow(array $row): ?array
    {
        $game = (string) ($row['game'] ?? '');
        $songId = filter_var($row['song_id'] ?? null, FILTER_VALIDATE_INT);
        $chart = $this->str($row['chart'] ?? null, 24);
        if (! ArcadeGames::exists($game) || $songId === false || $songId < 0 || ! $chart) {
            return null;
        }

        return [
            'game'        => $game,
            'song_id'     => $songId,
            'chart'       => $chart,
            'chart_order' => max(0, min(255, (int) ($row['chart_order'] ?? 0))),
            'score'       => $this->uint($row['score'] ?? null),
            'grade'       => $this->int($row['grade'] ?? null),
            'grade_label' => $this->str($row['grade_label'] ?? null, 16),
            'clear'       => $this->int($row['clear'] ?? null),
            'clear_label' => $this->str($row['clear_label'] ?? null, 16),
            'combo'       => $this->uint($row['combo'] ?? null),
            'play_count'  => $this->uint($row['play_count'] ?? null),
            'extra'       => is_array($row['extra'] ?? null) ? array_slice($row['extra'], 0, 20, true) : null,
        ];
    }

    private function str($v, int $max): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function int($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private function uint($v): ?int
    {
        return is_numeric($v) && $v >= 0 ? (int) min($v, 4294967295) : null;
    }

    /** ms-timestamp → Carbon (enkel plausibele datums). */
    private function time($v): ?Carbon
    {
        if (! is_numeric($v) || $v < 946684800000 || $v > (time() + 86400) * 1000) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $v)->setTimezone(config('app.timezone'));
    }
}

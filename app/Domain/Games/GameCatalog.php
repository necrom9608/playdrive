<?php

namespace App\Domain\Games;

use App\Jobs\FetchGameMetadata;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\VenueDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gamecatalogus van een venue, gevoed door Playscope.
 *
 *   report()  → Playscope meldt wat er geïnstalleerd is; nieuwe games komen in de catalogus
 *               (als "nieuw"), de koppeling toestel ↔ game wordt bijgewerkt en de speelplek
 *               krijgt automatisch de lijst met (zichtbare) games.
 *   payload() → de indeling die Playscope nodig heeft: categorieën en per game de
 *               categorieën, spelers en verborgen/uitgelicht.
 */
class GameCatalog
{
    /**
     * @param  array<int, array{id: string, name: string}>  $reported
     */
    public function report(VenueDevice $device, string $source, array $reported): array
    {
        $tenantId = (int) $device->tenant_id;
        $now = now();

        // Dubbels eruit (zelfde ID twee keer gemeld).
        $reported = collect($reported)
            ->map(fn ($g) => ['id' => trim((string) $g['id']), 'name' => trim((string) $g['name'])])
            ->filter(fn ($g) => $g['id'] !== '' && $g['name'] !== '')
            ->unique('id')
            ->values();

        $created = 0;

        DB::transaction(function () use ($device, $source, $reported, $tenantId, $now, &$created) {
            $existing = Game::query()
                ->where('tenant_id', $tenantId)
                ->where('source', $source)
                ->whereIn('external_id', $reported->pluck('id'))
                ->get()
                ->keyBy('external_id');

            $ids = [];
            foreach ($reported as $item) {
                $game = $existing->get($item['id']);

                if (! $game) {
                    $game = Game::query()->create([
                        'tenant_id'     => $tenantId,
                        'source'        => $source,
                        'external_id'   => $item['id'],
                        'name'          => $item['name'],
                        'first_seen_at' => $now,
                    ]);
                    $created++;
                } elseif ($game->name !== $item['name']) {
                    // Steam hernoemt soms (bv. "Counter-Strike: Global Offensive" → "Counter-Strike 2").
                    $game->forceFill(['name' => $item['name']])->save();
                }

                $ids[] = $game->id;
            }

            // Toestel ↔ game: enkel binnen dezelfde bron, zodat bv. TeknoParrot later
            // een Steam-melding niet wist.
            $current = $device->games()->where('games.source', $source)->pluck('games.id')->all();
            $remove = array_diff($current, $ids);
            $add = array_diff($ids, $current);

            if ($remove) {
                $device->games()->detach($remove);
            }
            if ($add) {
                $device->games()->attach(array_fill_keys($add, ['installed_at' => $now]));
            }

            $this->syncSetupGames($device);
        });

        // Nieuwe Steam-games: categorieën en spelers automatisch ophalen (na het antwoord).
        if ($created > 0 && $source === 'steam') {
            FetchGameMetadata::dispatchAfterResponse($tenantId);
        }

        return [
            'received' => $reported->count(),
            'new'      => $created,
        ];
    }

    /** Indeling voor Playscope: enkel de games die op dit toestel staan. */
    public function payload(VenueDevice $device): array
    {
        $categories = GameCategory::query()
            ->where('tenant_id', $device->tenant_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $games = $device->games()->with('categories:id')->get();

        // Enkel categorieën waarin minstens één game van dit toestel zit (lege filters hebben geen zin).
        $used = $games->flatMap(fn (Game $g) => $g->is_hidden ? [] : $g->categories->pluck('id'))->unique();

        return [
            'categories' => $categories
                ->filter(fn (GameCategory $c) => $used->contains($c->id))
                ->map(fn (GameCategory $c) => $c->toPublicArray())
                ->values(),
            'games' => $games->map(fn (Game $g) => [
                'source'            => $g->source,
                'id'                => $g->external_id,
                'categories'        => $g->categories->pluck('id')->values(),
                'hidden'            => (bool) $g->is_hidden,
                'featured'          => (bool) $g->is_featured,
                'local_players_max' => $g->local_players_max,
                'lan_multiplayer'   => $g->lan_multiplayer,
            ])->values(),
            // Welke spelersfilter Playscope gebruikt: "op één scherm" (console/couch) of LAN (pc).
            'setup_type' => $device->setup?->type,
        ];
    }

    /**
     * Speelplek van een Playscope-toestel: lijst met zichtbare games invullen (zoals die ook
     * op de kaart in Speelplekken en via de QR-code getoond wordt).
     */
    public function syncSetupGames(VenueDevice $device): void
    {
        if ($device->type !== 'playscope' || ! $device->setup_id) {
            return;
        }

        $setup = $device->setup()->first();
        if (! $setup) {
            return;
        }

        // Alle Playscope-toestellen op deze speelplek samen (meestal één).
        $names = Game::query()
            ->where('is_hidden', false)
            ->whereHas('devices', fn ($q) => $q
                ->where('venue_devices.setup_id', $setup->id)
                ->where('venue_devices.type', 'playscope'))
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $setup->forceFill(['games' => $names])->save();
    }

    /** Alle speelplekken waar deze games op staan opnieuw invullen (na verbergen/tonen in de backoffice). */
    public function resyncSetupsFor(Collection|array $gameIds): void
    {
        VenueDevice::query()
            ->where('type', 'playscope')
            ->whereNotNull('setup_id')
            ->whereHas('games', fn ($q) => $q->whereIn('games.id', collect($gameIds)->all()))
            ->get()
            ->unique('setup_id')
            ->each(fn (VenueDevice $d) => $this->syncSetupGames($d));
    }
}

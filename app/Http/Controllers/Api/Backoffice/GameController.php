<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Games\GameCatalog;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\VenueDevice;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gamecatalogus in de backoffice: games die Playscope op de PC's vond, indelen in
 * categorieën, aantal spelers, verborgen/uitgelicht. Plus beheer van de categorieën.
 */
class GameController extends Controller
{
    public function __construct(private readonly GameCatalog $catalog)
    {
    }

    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        $tenantId = $currentTenant->id();

        $games = Game::query()
            ->where('tenant_id', $tenantId)
            ->with(['categories:id', 'devices' => fn ($q) => $q->select('venue_devices.id', 'venue_devices.name', 'venue_devices.setup_id')->with('setup:id,name')])
            ->orderByRaw('reviewed_at IS NOT NULL')   // nieuwe eerst
            ->orderBy('name')
            ->get();

        $categories = GameCategory::query()
            ->where('tenant_id', $tenantId)
            ->withCount('games')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'games'      => $games->map(fn (Game $g) => $this->map($g))->values(),
            'categories' => $categories->map(fn (GameCategory $c) => [
                ...$c->toPublicArray(),
                'games_count' => (int) $c->games_count,
            ])->values(),
            'counts' => [
                'total'         => $games->count(),
                'new'           => $games->filter->isNew()->count(),
                'uncategorized' => $games->filter(fn (Game $g) => $g->categories->isEmpty() && ! $g->is_hidden)->count(),
                'hidden'        => $games->where('is_hidden', true)->count(),
            ],
            'local_player_options' => Game::LOCAL_PLAYER_OPTIONS,
        ]);
    }

    /** Aantal nieuwe games, voor de teller in het menu. */
    public function summary(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        return response()->json([
            'new' => Game::query()->where('tenant_id', $currentTenant->id())->whereNull('reviewed_at')->count(),
        ]);
    }

    /**
     * Eén game aanpassen. Elke aanpassing telt als "bekeken": de game is daarna niet meer nieuw.
     * Velden die niet meegestuurd worden, blijven ongewijzigd.
     */
    public function update(Request $request, CurrentTenant $currentTenant, Game $game): JsonResponse
    {
        $this->authorizeTenant($currentTenant, (int) $game->tenant_id);

        $data = $request->validate([
            'categories'        => ['sometimes', 'array'],
            'categories.*'      => ['integer', Rule::exists('game_categories', 'id')->where('tenant_id', $currentTenant->id())],
            'local_players_max' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:16'],
            'lan_multiplayer'   => ['sometimes', 'nullable', 'boolean'],
            'is_hidden'         => ['sometimes', 'boolean'],
            'is_featured'       => ['sometimes', 'boolean'],
            'reviewed'          => ['sometimes', 'boolean'],
        ]);

        $hiddenChanged = array_key_exists('is_hidden', $data) && (bool) $data['is_hidden'] !== (bool) $game->is_hidden;

        DB::transaction(function () use ($game, $data) {
            if (array_key_exists('categories', $data)) {
                $game->categories()->sync($data['categories']);
            }

            $fill = collect($data)->only(['local_players_max', 'lan_multiplayer', 'is_hidden', 'is_featured'])->all();
            $fill['reviewed_at'] = ($data['reviewed'] ?? true) ? ($game->reviewed_at ?? now()) : null;

            $game->forceFill($fill)->save();
        });

        // Verborgen games staan niet op de kaart van de speelplek.
        if ($hiddenChanged) {
            $this->catalog->resyncSetupsFor([$game->id]);
        }

        return response()->json($this->map($game->fresh(['categories:id', 'devices.setup:id,name'])));
    }

    /** Alle nieuwe games in één keer als bekeken markeren. */
    public function markAllReviewed(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $count = Game::query()
            ->where('tenant_id', $currentTenant->id())
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now()]);

        return response()->json(['ok' => true, 'updated' => $count]);
    }

    /** Uit de catalogus halen. Staat hij nog op een PC, dan komt hij bij de volgende melding terug (als nieuw). */
    public function destroy(CurrentTenant $currentTenant, Game $game): JsonResponse
    {
        $this->authorizeTenant($currentTenant, (int) $game->tenant_id);

        $game->delete();

        return response()->json(['ok' => true]);
    }

    // ── Categorieën ─────────────────────────────────────────────────────────

    public function storeCategory(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $this->validateCategory($request);
        $next = (int) GameCategory::query()->where('tenant_id', $currentTenant->id())->max('sort_order');

        $category = GameCategory::query()->create([
            ...$data,
            'tenant_id'  => $currentTenant->id(),
            'sort_order' => $next + 1,
        ]);

        return response()->json([...$category->toPublicArray(), 'games_count' => 0], 201);
    }

    public function updateCategory(Request $request, CurrentTenant $currentTenant, GameCategory $gameCategory): JsonResponse
    {
        $this->authorizeTenant($currentTenant, (int) $gameCategory->tenant_id);

        $gameCategory->fill($this->validateCategory($request))->save();

        return response()->json([...$gameCategory->toPublicArray(), 'games_count' => $gameCategory->games()->count()]);
    }

    public function reorderCategories(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $data = $request->validate([
            'items'      => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($data, $currentTenant) {
            foreach (array_values($data['items']) as $index => $item) {
                GameCategory::query()
                    ->where('tenant_id', $currentTenant->id())
                    ->where('id', (int) $item['id'])
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Games blijven bestaan, enkel de koppeling met deze categorie verdwijnt. */
    public function destroyCategory(CurrentTenant $currentTenant, GameCategory $gameCategory): JsonResponse
    {
        $this->authorizeTenant($currentTenant, (int) $gameCategory->tenant_id);

        $gameCategory->delete();

        return response()->json(['ok' => true]);
    }

    // ── Hulp ────────────────────────────────────────────────────────────────

    private function authorizeTenant(CurrentTenant $currentTenant, int $tenantId): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless($tenantId === (int) $currentTenant->id(), 404);
    }

    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    private function map(Game $game): array
    {
        // Waar staat hij: naam van de speelplek, anders van het toestel.
        $where = $game->devices
            ->map(fn (VenueDevice $d) => $d->setup?->name ?: ($d->name ?: "Toestel #{$d->id}"))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'id'                => $game->id,
            'source'            => $game->source,
            'source_label'      => Game::SOURCES[$game->source] ?? $game->source,
            'external_id'       => $game->external_id,
            'name'              => $game->name,
            'cover_url'         => $game->coverUrl(),
            'categories'        => $game->categories->pluck('id')->values(),
            'local_players_max' => $game->local_players_max,
            'lan_multiplayer'   => $game->lan_multiplayer,
            'is_hidden'         => (bool) $game->is_hidden,
            'is_featured'       => (bool) $game->is_featured,
            'is_new'            => $game->isNew(),
            'first_seen_at'     => $game->first_seen_at?->toIso8601String(),
            'installed_on'      => $where,
        ];
    }
}

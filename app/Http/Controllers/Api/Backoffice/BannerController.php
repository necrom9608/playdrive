<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Domain\Games\GameArtwork;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Setup;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Banners van games en speelplekken (Frontdesk → Zaal).
 *
 *   GET    /games/{game}/banner/candidates?q=&mame=    gevonden beelden
 *   POST   /games/{game}/banner                        {url, source} of multipart "file"
 *   DELETE /games/{game}/banner
 *   POST   /games/fetch-banners                        {force?, after_id?}  stukje games automatisch
 *   ... en hetzelfde voor /setups/{setup}/banner
 */
class BannerController extends Controller
{
    public function __construct(private readonly GameArtwork $artwork)
    {
    }

    // ---------------------------------------------------------------- games

    public function gameCandidates(Request $request, CurrentTenant $currentTenant, Game $game): JsonResponse
    {
        $this->authorize($currentTenant, $game);
        [$query, $mame] = $this->searchParams($request);

        return $this->candidatesResponse($game, $this->artwork->candidatesForGame($game, $query, $mame));
    }

    public function storeGame(Request $request, CurrentTenant $currentTenant, Game $game): JsonResponse
    {
        $this->authorize($currentTenant, $game);
        $this->store($request, $game);

        return response()->json($this->bannerPayload($game->fresh()));
    }

    public function destroyGame(CurrentTenant $currentTenant, Game $game): JsonResponse
    {
        $this->authorize($currentTenant, $game);
        $this->artwork->remove($game);

        return response()->json($this->bannerPayload($game->fresh()));
    }

    /** Automatisch banners zoeken voor een stukje games; de backoffice roept dit herhaaldelijk aan. */
    public function fetchGames(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        @set_time_limit(120);

        $data = $request->validate([
            'force'    => ['sometimes', 'boolean'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        $force = (bool) ($data['force'] ?? false);
        $afterId = (int) ($data['after_id'] ?? 0);

        $base = fn () => Game::query()
            ->where('tenant_id', $currentTenant->id())
            ->where(fn ($q) => $q->whereNull('banner_source')->orWhereNotIn('banner_source', ['upload', 'removed']))
            ->when(! $force, fn ($q) => $q->whereNull('banner_path')->whereNull('banner_fetched_at'))
            ->where('id', '>', $afterId);

        $batch = $base()->orderBy('id')->limit(6)->get();

        $statuses = [];
        foreach ($batch as $game) {
            $status = $this->artwork->auto($game, $force);
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            usleep(200_000);
        }

        $lastId = (int) ($batch->last()?->id ?? $afterId);

        return response()->json([
            'processed' => $batch->count(),
            'statuses'  => $statuses,
            'remaining' => $base()->where('id', '>', $lastId)->count(),
            'last_id'   => $lastId,
            'games'     => Game::query()->whereIn('id', $batch->pluck('id'))->get()
                ->map(fn (Game $g) => ['id' => $g->id, ...$this->bannerPayload($g)])->values(),
        ]);
    }

    // ---------------------------------------------------------------- speelplekken

    public function setupCandidates(Request $request, CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorize($currentTenant, $setup);
        [$query, $mame] = $this->searchParams($request);

        return $this->candidatesResponse($setup, $this->artwork->candidatesForSetup($setup, $query, $mame));
    }

    public function storeSetup(Request $request, CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorize($currentTenant, $setup);
        $this->store($request, $setup);

        return response()->json($this->bannerPayload($setup->fresh()));
    }

    public function destroySetup(CurrentTenant $currentTenant, Setup $setup): JsonResponse
    {
        $this->authorize($currentTenant, $setup);
        $this->artwork->remove($setup);

        return response()->json($this->bannerPayload($setup->fresh()));
    }

    // ---------------------------------------------------------------- hulpjes

    /** Velden die de backoffice per game/setup meekrijgt. */
    public static function bannerPayload(Model $target): array
    {
        $banner = GameArtwork::bannerFor($target);

        return [
            'banner_url'     => $banner['url'] ?? null,
            'banner_ratio'   => $banner['ratio'] ?? null,
            'banner_source'  => $target->banner_source,
            'banner_stored'  => (bool) $target->banner_path,
            'banner_checked' => $target->banner_fetched_at !== null,
        ];
    }

    private function store(Request $request, Model $target): void
    {
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            ], [
                'file.mimes' => 'Kies een jpg-, png-, webp- of gif-bestand.',
                'file.max'   => 'Het bestand mag maximaal 8 MB zijn.',
            ]);

            abort_unless($this->artwork->storeUpload($target, $request->file('file')), 422, 'Dit bestand kon niet als afbeelding gelezen worden.');

            return;
        }

        $data = $request->validate([
            'url'    => ['required', 'string', 'max:1000'],
            'source' => ['required', 'string', Rule::in(array_keys(GameArtwork::SOURCES))],
        ]);

        abort_unless($this->artwork->allowedUrl($data['url']), 422, 'Deze afbeelding komt niet van een gekende bron.');
        abort_unless($this->artwork->storeFromUrl($target, $data['url'], $data['source']), 422, 'Afbeelding ophalen mislukt. Probeer een andere.');
    }

    private function searchParams(Request $request): array
    {
        $data = $request->validate([
            'q'    => ['nullable', 'string', 'max:120'],
            'mame' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
        ], [
            'mame.regex' => 'Een MAME-romnaam bestaat enkel uit letters, cijfers en _ (bv. motocrgo).',
        ]);

        return [
            filled($data['q'] ?? null) ? trim($data['q']) : null,
            filled($data['mame'] ?? null) ? strtolower(trim($data['mame'])) : null,
        ];
    }

    private function candidatesResponse(Model $target, array $candidates): JsonResponse
    {
        return response()->json([
            'candidates'            => $candidates,
            'steamgriddb_available' => GameArtwork::steamGridDbConfigured(),
            ...$this->bannerPayload($target),
        ]);
    }

    private function authorize(CurrentTenant $currentTenant, Model $target): void
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_unless((int) $target->tenant_id === (int) $currentTenant->id(), 404);
    }
}

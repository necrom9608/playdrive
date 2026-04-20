<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\RegionSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    // ------------------------------------------------------------------
    // Regio's
    // ------------------------------------------------------------------

    public function index(): JsonResponse
    {
        $regions = Region::query()
            ->withCount('seasons')
            ->orderBy('name')
            ->get()
            ->map(fn (Region $r) => $this->mapRegion($r));

        return response()->json(['regions' => $regions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:regions,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $region = Region::query()->create($data);

        return response()->json(['region' => $this->mapRegion($region)], 201);
    }

    public function update(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:regions,code,' . $region->id],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $region->update($data);

        return response()->json(['region' => $this->mapRegion($region)]);
    }

    public function destroy(Region $region): JsonResponse
    {
        $region->delete();

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Seizoenen per regio
    // ------------------------------------------------------------------

    public function seasons(Region $region): JsonResponse
    {
        $seasons = $region->seasons()
            ->orderBy('date_from')
            ->get()
            ->map(fn (RegionSeason $s) => $this->mapSeason($s));

        return response()->json(['seasons' => $seasons]);
    }

    public function storeSeason(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'season_key'  => ['required', 'string', 'max:50'],
            'season_name' => ['required', 'string', 'max:100'],
            'date_from'   => ['required', 'date'],
            'date_until'  => ['required', 'date', 'after_or_equal:date_from'],
            'priority'    => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $season = $region->seasons()->create([
            'region_code' => $region->code,
            'season_key'  => $data['season_key'],
            'season_name' => $data['season_name'],
            'date_from'   => $data['date_from'],
            'date_until'  => $data['date_until'],
            'priority'    => $data['priority'] ?? 20,
        ]);

        return response()->json(['season' => $this->mapSeason($season)], 201);
    }

    public function updateSeason(Request $request, Region $region, RegionSeason $season): JsonResponse
    {
        abort_if($season->region_code !== $region->code, 404);

        $data = $request->validate([
            'season_key'  => ['required', 'string', 'max:50'],
            'season_name' => ['required', 'string', 'max:100'],
            'date_from'   => ['required', 'date'],
            'date_until'  => ['required', 'date', 'after_or_equal:date_from'],
            'priority'    => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $season->update([
            'season_key'  => $data['season_key'],
            'season_name' => $data['season_name'],
            'date_from'   => $data['date_from'],
            'date_until'  => $data['date_until'],
            'priority'    => $data['priority'] ?? 20,
        ]);

        return response()->json(['season' => $this->mapSeason($season)]);
    }

    public function destroySeason(Region $region, RegionSeason $season): JsonResponse
    {
        abort_if($season->region_code !== $region->code, 404);

        $season->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Kopieer alle seizoenen van een jaar naar het volgende jaar.
     * Handig voor het jaarlijkse onderhoud van de vakantiekalender.
     */
    public function copySeasons(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'from_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'to_year'   => ['required', 'integer', 'min:2020', 'max:2050'],
        ]);

        $fromYear = $data['from_year'];
        $toYear   = $data['to_year'];
        $diff     = $toYear - $fromYear;

        $seasons = $region->seasons()
            ->whereYear('date_from', $fromYear)
            ->get();

        $created = 0;

        DB::transaction(function () use ($region, $seasons, $diff, &$created) {
            foreach ($seasons as $season) {
                $region->seasons()->create([
                    'region_code' => $region->code,
                    'season_key'  => $season->season_key,
                    'season_name' => $season->season_name,
                    'date_from'   => $season->date_from->addYears($diff),
                    'date_until'  => $season->date_until->addYears($diff),
                    'priority'    => $season->priority,
                ]);
                $created++;
            }
        });

        return response()->json(['created' => $created]);
    }

    // ------------------------------------------------------------------
    // Mapping helpers
    // ------------------------------------------------------------------

    private function mapRegion(Region $region): array
    {
        return [
            'id'           => $region->id,
            'code'         => $region->code,
            'name'         => $region->name,
            'seasons_count' => $region->seasons_count ?? null,
        ];
    }

    private function mapSeason(RegionSeason $season): array
    {
        return [
            'id'          => $season->id,
            'region_code' => $season->region_code,
            'season_key'  => $season->season_key,
            'season_name' => $season->season_name,
            'date_from'   => $season->date_from?->format('Y-m-d'),
            'date_until'  => $season->date_until?->format('Y-m-d'),
            'priority'    => $season->priority,
        ];
    }
}

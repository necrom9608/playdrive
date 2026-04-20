<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\OpeningException;
use App\Models\OpeningHour;
use App\Models\RegionSeason;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningHoursController extends Controller
{
    /**
     * Geeft de volledige configuratie terug die de backoffice nodig heeft:
     * - openingsuren per season_key + weekdag
     * - vakantieperiodes van de regio
     * - uitzonderingen van de tenant
     */
    public function index(CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $hours = OpeningHour::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('season_key')
            ->orderBy('weekday')
            ->get()
            ->map(fn (OpeningHour $h) => $this->mapHour($h));

        $seasons = RegionSeason::query()
            ->where('region_code', $tenant->region_code)
            ->orderBy('date_from')
            ->get()
            ->map(fn (RegionSeason $s) => $this->mapSeason($s));

        $exceptions = OpeningException::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('date')
            ->get()
            ->map(fn (OpeningException $e) => $this->mapException($e));

        return response()->json([
            'region_code' => $tenant->region_code,
            'hours'       => $hours,
            'seasons'     => $seasons,
            'exceptions'  => $exceptions,
        ]);
    }

    /**
     * Slaat de openingsuren op via bulk upsert.
     * Verwacht een array van uren met season_key, weekday, is_open, open_from, open_until.
     */
    public function saveHours(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $data = $request->validate([
            'hours'               => ['required', 'array'],
            'hours.*.season_key'  => ['required', 'string', 'max:50'],
            'hours.*.weekday'     => ['required', 'integer', 'min:1', 'max:7'],
            'hours.*.is_open'     => ['required', 'boolean'],
            'hours.*.open_from'   => ['nullable', 'date_format:H:i'],
            'hours.*.open_until'  => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($tenant, $data) {
            foreach ($data['hours'] as $row) {
                OpeningHour::query()->updateOrCreate(
                    [
                        'tenant_id'  => $tenant->id,
                        'season_key' => $row['season_key'],
                        'weekday'    => $row['weekday'],
                    ],
                    [
                        'is_open'    => $row['is_open'],
                        'open_from'  => $row['is_open'] ? ($row['open_from'] ?? null) : null,
                        'open_until' => $row['is_open'] ? ($row['open_until'] ?? null) : null,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Maakt een nieuwe uitzondering aan of werkt een bestaande bij.
     */
    public function storeException(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');

        $tenant = $currentTenant->tenant;

        $data = $request->validate([
            'date'       => ['required', 'date'],
            'is_open'    => ['required', 'boolean'],
            'open_from'  => ['nullable', 'date_format:H:i'],
            'open_until' => ['nullable', 'date_format:H:i'],
            'label'      => ['nullable', 'string', 'max:100'],
        ]);

        $exception = OpeningException::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'date' => $data['date']],
            [
                'is_open'    => $data['is_open'],
                'open_from'  => $data['is_open'] ? ($data['open_from'] ?? null) : null,
                'open_until' => $data['is_open'] ? ($data['open_until'] ?? null) : null,
                'label'      => $data['label'] ?? null,
            ]
        );

        return response()->json(['exception' => $this->mapException($exception)], 201);
    }

    /**
     * Verwijdert een uitzondering.
     */
    public function destroyException(OpeningException $exception, CurrentTenant $currentTenant): JsonResponse
    {
        abort_unless($currentTenant->exists(), 404, 'Geen geldige tenant gevonden.');
        abort_if($exception->tenant_id !== $currentTenant->tenant->id, 403);

        $exception->delete();

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Mapping helpers
    // ------------------------------------------------------------------

    private function mapHour(OpeningHour $hour): array
    {
        return [
            'id'         => $hour->id,
            'season_key' => $hour->season_key,
            'weekday'    => $hour->weekday,
            'is_open'    => $hour->is_open,
            'open_from'  => $hour->open_from,
            'open_until' => $hour->open_until,
        ];
    }

    private function mapSeason(RegionSeason $season): array
    {
        return [
            'id'          => $season->id,
            'season_key'  => $season->season_key,
            'season_name' => $season->season_name,
            'date_from'   => $season->date_from?->format('Y-m-d'),
            'date_until'  => $season->date_until?->format('Y-m-d'),
            'priority'    => $season->priority,
        ];
    }

    private function mapException(OpeningException $e): array
    {
        return [
            'id'         => $e->id,
            'date'       => $e->date?->format('Y-m-d'),
            'is_open'    => $e->is_open,
            'open_from'  => $e->open_from,
            'open_until' => $e->open_until,
            'label'      => $e->label,
        ];
    }
}

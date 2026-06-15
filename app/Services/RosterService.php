<?php

namespace App\Services;

use App\Models\RegionSeason;
use App\Models\RosterAssignment;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Beheert het slot-gebaseerde personeels-uurrooster.
 *
 *  1. Het ALGEMENE rooster (roster_slots) is de "vraag" per season_key x
 *     weekdag: tijd, rol, gewenst aantal, staand commentaar, met optionele
 *     standaard-invullers.
 *  2. Voor een CONCRETE week worden die slots uitgerold naar shiften
 *     (roster_shifts), waarbij per dag het actieve seizoen bepaald wordt uit
 *     RegionSeason (vakantie = eigen set slots). Mensen worden in de blokken
 *     toegewezen (roster_assignments).
 *
 * Genereren raakt enkel dagen aan die nog géén shift hebben → weekaanpassingen
 * blijven behouden. resetWeek() wist de week en rolt opnieuw uit.
 */
class RosterService
{
    /** Maandag van de week waarin $date valt. */
    public function weekStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY);
    }

    /** Actieve medewerkers van een tenant. */
    public function staff(int $tenantId): Collection
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    // ==================================================================
    //  Seizoen-resolutie (gelijk aan de openingsuren-conventie)
    // ==================================================================

    /**
     * De seizoenen van de regio, gesorteerd zoals de rest van de app:
     * hoogste priority eerst, dan vroegste date_from. De eerste die een datum
     * bevat wint. Geen match → 'regular'.
     */
    public function seasonsForRegion(?string $regionCode): Collection
    {
        if (!$regionCode) {
            return collect();
        }

        return RegionSeason::query()
            ->where('region_code', $regionCode)
            ->orderByDesc('priority')
            ->orderBy('date_from')
            ->get();
    }

    /** Resolveert één datum (Y-m-d) naar een season_key. */
    public function seasonKeyForDate(string $date, Collection $seasons): string
    {
        foreach ($seasons as $season) {
            $from  = $season->date_from?->toDateString();
            $until = $season->date_until?->toDateString();
            if ($from && $until && $date >= $from && $date <= $until) {
                return $season->season_key;
            }
        }

        return 'regular';
    }

    /** Map [datum => season_key] voor een lijst datums. */
    public function seasonMap(array $dates, ?string $regionCode): array
    {
        $seasons = $this->seasonsForRegion($regionCode);
        $out = [];
        foreach ($dates as $d) {
            $out[$d] = $this->seasonKeyForDate($d, $seasons);
        }

        return $out;
    }

    // ==================================================================
    //  Week ophalen / genereren / resetten
    // ==================================================================

    /** De 7 datums (Y-m-d) van de week. */
    public function weekDates(CarbonImmutable $weekStart): array
    {
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $weekStart->addDays($i)->toDateString();
        }

        return $dates;
    }

    /** Shiften van de week, met toewijzingen geladen. */
    public function weekShifts(int $tenantId, CarbonImmutable $weekStart): Collection
    {
        $dates = $this->weekDates($weekStart);

        return RosterShift::query()
            ->with('assignments')
            ->where('tenant_id', $tenantId)
            ->whereIn('date', $dates)
            ->orderBy('date')
            ->orderBy('starts_at')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Rolt slots uit naar shiften voor de week. Dagen die al een shift hebben
     * worden overgeslagen. Geeft het aantal aangemaakte shiften terug.
     */
    public function generateWeek(Tenant $tenant, CarbonImmutable $weekStart): int
    {
        $dates    = $this->weekDates($weekStart);
        $seasonOf = $this->seasonMap($dates, $tenant->region_code);

        // Slots van deze tenant, geïndexeerd op season_key + weekdag.
        $slots = RosterSlot::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn ($s) => $s->season_key . '|' . $s->weekday);

        // Standaard-invullers per slot.
        $defaults = \App\Models\RosterSlotDefault::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->groupBy('slot_id');

        // Dagen die al shiften hebben → niet aanraken.
        $existingDates = RosterShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('date', $dates)
            ->pluck('date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())
            ->unique()
            ->flip();

        return DB::transaction(function () use ($dates, $seasonOf, $slots, $defaults, $existingDates, $tenant) {
            $created = 0;

            foreach ($dates as $date) {
                if ($existingDates->has($date)) {
                    continue;
                }

                $weekday   = CarbonImmutable::parse($date)->dayOfWeekIso; // 1..7
                $seasonKey = $seasonOf[$date] ?? 'regular';
                $daySlots  = $slots->get($seasonKey . '|' . $weekday);

                if (!$daySlots) {
                    continue;
                }

                foreach ($daySlots as $slot) {
                    $shift = RosterShift::query()->create([
                        'tenant_id'     => $tenant->id,
                        'date'          => $date,
                        'season_key'    => $seasonKey,
                        'slot_id'       => $slot->id,
                        'role_id'       => $slot->role_id,
                        'starts_at'     => $slot->starts_at,
                        'ends_at'       => $slot->ends_at,
                        'desired_count' => $slot->desired_count,
                        'comment'       => $slot->comment,
                        'note'          => null,
                        'status'        => 'scheduled',
                        'source'        => RosterShift::SOURCE_TEMPLATE,
                        'sort_order'    => $slot->sort_order,
                    ]);
                    $created++;

                    // Standaard-invullers automatisch toewijzen.
                    foreach (($defaults->get($slot->id) ?? collect()) as $def) {
                        RosterAssignment::query()->create([
                            'tenant_id' => $tenant->id,
                            'shift_id'  => $shift->id,
                            'user_id'   => $def->user_id,
                            'source'    => RosterAssignment::SOURCE_TEMPLATE,
                        ]);
                    }
                }
            }

            return $created;
        });
    }

    /** Wist de week (shiften + toewijzingen) en rolt opnieuw uit. */
    public function resetWeek(Tenant $tenant, CarbonImmutable $weekStart): int
    {
        $dates = $this->weekDates($weekStart);

        DB::transaction(function () use ($tenant, $dates) {
            $shiftIds = RosterShift::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('date', $dates)
                ->pluck('id');

            RosterAssignment::query()->whereIn('shift_id', $shiftIds)->delete();
            RosterShift::query()->whereIn('id', $shiftIds)->delete();
        });

        return $this->generateWeek($tenant, $weekStart);
    }
}

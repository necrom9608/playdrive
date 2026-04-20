<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * opening-hours v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Importeert de bestaande Game-INN openingsuren-data naar het nieuwe model.
 *
 * Wat er gebeurt:
 *   1. Regio BE-VL aanmaken (als nog niet bestaat)
 *   2. Schoolvakanties + zomervakantie importeren als region_seasons voor BE-VL
 *   3. Openingsuren importeren als opening_hours voor tenant ID 1 (Game-INN)
 *   4. Uitzonderingen importeren als opening_exceptions voor tenant ID 1
 *
 * Uitvoeren:
 *   php artisan db:seed --class=GameInnOpeningHoursSeeder
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GameInnOpeningHoursSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $regionCode = 'BE-VL';

        // ──────────────────────────────────────────────────────────────────
        // 1. Regio BE-VL
        // ──────────────────────────────────────────────────────────────────
        DB::table('regions')->upsert(
            [
                'code' => $regionCode,
                'name' => 'Vlaanderen',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['code'],
            ['name', 'updated_at'],
        );

        // Koppel tenant 1 aan BE-VL
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['region_code' => $regionCode]);

        // ──────────────────────────────────────────────────────────────────
        // 2. Region seasons (uit game-inn_opening_seasons.csv)
        //    Enkel school_vac en summer — regular heeft geen vaste datums
        // ──────────────────────────────────────────────────────────────────
        $seasons = [
            // summer
            ['season_key' => 'summer', 'season_name' => 'Zomervakantie',   'date_from' => '2026-07-01', 'date_until' => '2026-08-31', 'priority' => 30],
            ['season_key' => 'summer', 'season_name' => 'Zomervakantie',   'date_from' => '2027-07-01', 'date_until' => '2027-08-31', 'priority' => 30],
            // school_vac
            ['season_key' => 'school_vac', 'season_name' => 'Herfstvakantie',  'date_from' => '2025-10-27', 'date_until' => '2025-11-02', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Kerstvakantie',   'date_from' => '2025-12-22', 'date_until' => '2026-01-04', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Krokusvakantie',  'date_from' => '2026-02-16', 'date_until' => '2026-02-22', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Paasvakantie',    'date_from' => '2026-04-06', 'date_until' => '2026-04-19', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Herfstvakantie',  'date_from' => '2026-11-02', 'date_until' => '2026-11-08', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Kerstvakantie',   'date_from' => '2026-12-21', 'date_until' => '2027-01-03', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Krokusvakantie',  'date_from' => '2027-02-08', 'date_until' => '2027-02-14', 'priority' => 20],
            ['season_key' => 'school_vac', 'season_name' => 'Paasvakantie',    'date_from' => '2027-03-29', 'date_until' => '2027-04-11', 'priority' => 20],
        ];

        foreach ($seasons as $season) {
            // Voorkom duplicaten op basis van region_code + season_name + date_from
            $exists = DB::table('region_seasons')
                ->where('region_code', $regionCode)
                ->where('season_name', $season['season_name'])
                ->where('date_from', $season['date_from'])
                ->exists();

            if (! $exists) {
                DB::table('region_seasons')->insert([
                    'region_code' => $regionCode,
                    'season_key'  => $season['season_key'],
                    'season_name' => $season['season_name'],
                    'date_from'   => $season['date_from'],
                    'date_until'  => $season['date_until'],
                    'priority'    => $season['priority'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // ──────────────────────────────────────────────────────────────────
        // 3. Opening hours voor tenant 1 (uit game-inn_opening_hours.csv)
        //    Weekdagen: 1=ma, 2=di, 3=wo, 4=do, 5=vr, 6=za, 7=zo (ISO 8601)
        //    Dagen die niet voorkomen in de CSV = gesloten (is_open = false)
        // ──────────────────────────────────────────────────────────────────
        $hours = [
            // regular: alleen wo, vr, za, zo open
            ['season_key' => 'regular', 'weekday' => 1, 'is_open' => false, 'open_from' => null,    'open_until' => null],
            ['season_key' => 'regular', 'weekday' => 2, 'is_open' => false, 'open_from' => null,    'open_until' => null],
            ['season_key' => 'regular', 'weekday' => 3, 'is_open' => true,  'open_from' => '13:30', 'open_until' => '18:00'],
            ['season_key' => 'regular', 'weekday' => 4, 'is_open' => false, 'open_from' => null,    'open_until' => null],
            ['season_key' => 'regular', 'weekday' => 5, 'is_open' => true,  'open_from' => '18:00', 'open_until' => '22:00'],
            ['season_key' => 'regular', 'weekday' => 6, 'is_open' => true,  'open_from' => '14:00', 'open_until' => '22:00'],
            ['season_key' => 'regular', 'weekday' => 7, 'is_open' => true,  'open_from' => '14:00', 'open_until' => '21:00'],
            // school_vac: elke dag open
            ['season_key' => 'school_vac', 'weekday' => 1, 'is_open' => true, 'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'school_vac', 'weekday' => 2, 'is_open' => true, 'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'school_vac', 'weekday' => 3, 'is_open' => true, 'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'school_vac', 'weekday' => 4, 'is_open' => true, 'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'school_vac', 'weekday' => 5, 'is_open' => true, 'open_from' => '10:30', 'open_until' => '22:00'],
            ['season_key' => 'school_vac', 'weekday' => 6, 'is_open' => true, 'open_from' => '14:00', 'open_until' => '22:00'],
            ['season_key' => 'school_vac', 'weekday' => 7, 'is_open' => true, 'open_from' => '14:00', 'open_until' => '21:00'],
            // summer: wo t/m zo open (ma + di gesloten, net als regular)
            ['season_key' => 'summer', 'weekday' => 1, 'is_open' => false, 'open_from' => null,    'open_until' => null],
            ['season_key' => 'summer', 'weekday' => 2, 'is_open' => false, 'open_from' => null,    'open_until' => null],
            ['season_key' => 'summer', 'weekday' => 3, 'is_open' => true,  'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'summer', 'weekday' => 4, 'is_open' => true,  'open_from' => '10:30', 'open_until' => '18:00'],
            ['season_key' => 'summer', 'weekday' => 5, 'is_open' => true,  'open_from' => '10:30', 'open_until' => '22:00'],
            ['season_key' => 'summer', 'weekday' => 6, 'is_open' => true,  'open_from' => '14:00', 'open_until' => '22:00'],
            ['season_key' => 'summer', 'weekday' => 7, 'is_open' => true,  'open_from' => '14:00', 'open_until' => '21:00'],
        ];

        foreach ($hours as $hour) {
            DB::table('opening_hours')->upsert(
                [
                    'tenant_id'  => $tenantId,
                    'season_key' => $hour['season_key'],
                    'weekday'    => $hour['weekday'],
                    'is_open'    => $hour['is_open'],
                    'open_from'  => $hour['open_from'],
                    'open_until' => $hour['open_until'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['tenant_id', 'season_key', 'weekday'],
                ['is_open', 'open_from', 'open_until', 'updated_at'],
            );
        }

        // ──────────────────────────────────────────────────────────────────
        // 4. Opening exceptions voor tenant 1 (uit game-inn_opening_exceptions.csv)
        //    Originele kolom: exception_date → date, status → is_open, note → label
        // ──────────────────────────────────────────────────────────────────
        $exceptions = [
            ['date' => '2026-05-01', 'is_open' => true,  'open_from' => '10:30', 'open_until' => '22:00', 'label' => 'Dag van de Arbeid (aangepaste opening)'],
            ['date' => '2026-05-14', 'is_open' => true,  'open_from' => '10:30', 'open_until' => '18:00', 'label' => 'Hemelvaart'],
            ['date' => '2026-05-15', 'is_open' => true,  'open_from' => '10:30', 'open_until' => '22:00', 'label' => 'Hemelvaart (brug)'],
            ['date' => '2026-12-24', 'is_open' => false, 'open_from' => null,    'open_until' => null,    'label' => 'Kerstavond'],
            ['date' => '2026-12-25', 'is_open' => false, 'open_from' => null,    'open_until' => null,    'label' => 'Kerstmis'],
            ['date' => '2027-01-01', 'is_open' => false, 'open_from' => null,    'open_until' => null,    'label' => 'Nieuwjaar'],
        ];

        foreach ($exceptions as $exception) {
            DB::table('opening_exceptions')->upsert(
                [
                    'tenant_id'  => $tenantId,
                    'date'       => $exception['date'],
                    'is_open'    => $exception['is_open'],
                    'open_from'  => $exception['open_from'],
                    'open_until' => $exception['open_until'],
                    'label'      => $exception['label'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['tenant_id', 'date'],
                ['is_open', 'open_from', 'open_until', 'label', 'updated_at'],
            );
        }

        $this->command->info('✓ Regio BE-VL aangemaakt');
        $this->command->info('✓ 10 vakantieperiodes geïmporteerd als region_seasons');
        $this->command->info('✓ 21 opening_hours geïmporteerd voor tenant ' . $tenantId);
        $this->command->info('✓ 6 opening_exceptions geïmporteerd voor tenant ' . $tenantId);
        $this->command->info('✓ Tenant ' . $tenantId . ' gekoppeld aan regio ' . $regionCode);
    }
}

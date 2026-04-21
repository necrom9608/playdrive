<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * booking-form v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Vult de booking-form configuratietabellen voor tenant 1 (Game-INN).
 *
 * Veronderstellingen over de bestaande data:
 *   Event-types (tenant 1):
 *     ID 1 — Verjaardagsfeest
 *     ID 2 — Vrijgezellenfeest
 *     ID 3 — Teambuilding
 *     ID 4 — Bedrijfsevent
 *     ID 5 — Vrij bezoek
 *     ID 6 — Iets anders
 *
 *   Catering-opties (tenant 1):
 *     ID 1 — Pannenkoeken       (auto bij kinderen-verjaardag)
 *     ID 2 — Pizzabuffet        (keuze bij volwassenen-verjaardag)
 *     ID 3 — Broodjeslunch      (keuze bij volwassenen-verjaardag)
 *
 *   Stay-opties (tenant 1):
 *     ID 1 — 2 uur
 *     ID 2 — Halve dag
 *     ID 3 — Hele dag
 *
 * Aanpassen als de IDs in jouw database afwijken.
 *
 * Uitvoeren:
 *   php artisan db:seed --class=GameInnBookingFormSeeder
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GameInnBookingFormSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $now      = now();

        // ──────────────────────────────────────────────────────────────────
        // 1. Globale formulier-config
        // ──────────────────────────────────────────────────────────────────
        DB::table('booking_form_configs')->upsert(
            [
                'tenant_id'                     => $tenantId,
                'is_active'                     => true,
                'show_participant_children'     => true,
                'show_participant_adults'       => true,
                'show_participant_supervisors'  => true,   // begeleiders zijn relevant bij Game-INN
                'outside_hours_warning_enabled' => true,
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ],
            ['tenant_id'],
            [
                'is_active',
                'show_participant_children',
                'show_participant_adults',
                'show_participant_supervisors',
                'outside_hours_warning_enabled',
                'updated_at',
            ],
        );

        // ──────────────────────────────────────────────────────────────────
        // 2. Event-type configuraties
        // ──────────────────────────────────────────────────────────────────
        $eventTypeConfigs = [

            // Verjaardagsfeest — kinderen/volwassenen keuze met catering-logica
            [
                'event_type_id' => 1,
                'show_in_form'  => true,
                'audience_mode' => 'children_adults',
                'audience_options' => json_encode([
                    [
                        'audience'                => 'children',
                        'label'                   => 'Kinderen / jongeren',
                        // Pannenkoeken worden automatisch gekoppeld, geen keuze
                        'auto_catering_option_id' => 1,
                    ],
                    [
                        'audience'        => 'adults',
                        'label'           => 'Volwassenen',
                        // Gebruiker kiest: geen catering (null), pizzabuffet of broodjeslunch
                        'catering_choices' => [null, 2, 3],
                    ],
                ]),
            ],

            // Vrijgezellenfeest — altijd volwassenen, catering naar keuze
            [
                'event_type_id' => 2,
                'show_in_form'  => true,
                'audience_mode' => 'adults_only',
                'audience_options' => json_encode([
                    [
                        'audience'         => 'default',
                        'label'            => 'Volwassenen',
                        'catering_choices' => [null, 2, 3],
                    ],
                ]),
            ],

            // Teambuilding — geen doelgroepvraag, geen automatische catering
            [
                'event_type_id'    => 3,
                'show_in_form'     => true,
                'audience_mode'    => 'none',
                'audience_options' => null,
            ],

            // Bedrijfsevent — geen doelgroepvraag, geen automatische catering
            [
                'event_type_id'    => 4,
                'show_in_form'     => true,
                'audience_mode'    => 'none',
                'audience_options' => null,
            ],

            // Vrij bezoek — geen doelgroepvraag
            [
                'event_type_id'    => 5,
                'show_in_form'     => true,
                'audience_mode'    => 'none',
                'audience_options' => null,
            ],

            // Iets anders — zichtbaar maar geen extra opties
            [
                'event_type_id'    => 6,
                'show_in_form'     => true,
                'audience_mode'    => 'none',
                'audience_options' => null,
            ],
        ];

        foreach ($eventTypeConfigs as $config) {
            DB::table('booking_form_event_type_configs')->upsert(
                array_merge($config, [
                    'tenant_id'  => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
                ['tenant_id', 'event_type_id'],
                ['show_in_form', 'audience_mode', 'audience_options', 'updated_at'],
            );
        }

        // ──────────────────────────────────────────────────────────────────
        // 3. Stay-option configuraties met minimumtarieven buiten openingsuren
        // ──────────────────────────────────────────────────────────────────
        $stayOptionConfigs = [
            // 2 uur — minimum €300 buiten openingsuren
            [
                'stay_option_id'                  => 1,
                'show_in_form'                    => true,
                'min_revenue_outside_hours_cents' => 30000,
            ],
            // Halve dag — minimum €600 buiten openingsuren
            [
                'stay_option_id'                  => 2,
                'show_in_form'                    => true,
                'min_revenue_outside_hours_cents' => 60000,
            ],
            // Hele dag — minimum €1000 buiten openingsuren
            [
                'stay_option_id'                  => 3,
                'show_in_form'                    => true,
                'min_revenue_outside_hours_cents' => 100000,
            ],
        ];

        foreach ($stayOptionConfigs as $config) {
            DB::table('booking_form_stay_option_configs')->upsert(
                array_merge($config, [
                    'tenant_id'  => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
                ['tenant_id', 'stay_option_id'],
                ['show_in_form', 'min_revenue_outside_hours_cents', 'updated_at'],
            );
        }

        $this->command->info('✓ Booking form config aangemaakt voor tenant ' . $tenantId . ' (Game-INN)');
        $this->command->info('✓ 6 event-type configs aangemaakt');
        $this->command->info('✓ 3 stay-option configs aangemaakt (incl. minimumtarieven)');
    }
}

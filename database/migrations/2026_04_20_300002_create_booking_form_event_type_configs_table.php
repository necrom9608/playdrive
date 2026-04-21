<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking-form v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Configuratie per event-type voor het reservatieformulier.
 * Bepaalt of een event-type zichtbaar is in het formulier, of er een
 * doelgroepvraag getoond wordt (kinderen/volwassenen), en welke catering
 * automatisch gekoppeld of aangeboden wordt per doelgroep.
 *
 * audience_mode:
 *   'none'             — geen doelgroepvraag (bv. teambuilding, bedrijfsevent)
 *   'children_adults'  — keuze kinderen / volwassenen (bv. verjaardagsfeest)
 *   'adults_only'      — altijd volwassenen, geen keuze maar wel catering-opties
 *
 * audience_options (JSON):
 *   Array van objecten per doelgroep. Structuur per object:
 *   {
 *     "audience": "children" | "adults" | "default",
 *     "label": "Kinderen / jongeren",
 *
 *     // Optie A: automatisch één catering koppelen (geen keuze voor gebruiker)
 *     "auto_catering_option_id": 3,
 *
 *     // Optie B: gebruiker kiest zelf uit een lijst van catering_option_id's
 *     // null = geen catering
 *     "catering_choices": [null, 4, 5]
 *   }
 *
 * Voorbeelden voor Game-INN verjaardagsfeest:
 *   audience_mode = 'children_adults'
 *   audience_options = [
 *     { "audience": "children", "label": "Kinderen / jongeren",
 *       "auto_catering_option_id": 1 },          ← pannenkoeken automatisch
 *     { "audience": "adults",   "label": "Volwassenen",
 *       "catering_choices": [null, 2, 3] }        ← geen / pizza / broodjes
 *   ]
 * ─────────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_form_event_type_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_type_id')->constrained()->onDelete('cascade');

            // Zichtbaar in het formulier
            $table->boolean('show_in_form')->default(true);

            // Bepaalt of en hoe de doelgroepvraag getoond wordt
            $table->string('audience_mode', 20)->default('none');
            // Waarden: 'none' | 'children_adults' | 'adults_only'

            // JSON-configuratie per doelgroep (zie docblock hierboven)
            $table->json('audience_options')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'event_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_form_event_type_configs');
    }
};

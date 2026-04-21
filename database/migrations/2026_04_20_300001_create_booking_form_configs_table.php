<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking-form v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Centrale configuratietabel voor het reservatieformulier per tenant.
 * Één record per tenant. Bepaalt welke persoonsgroepen gevraagd worden,
 * of het formulier actief is, en of de privé/buiten-uren waarschuwing
 * met minimumtarieven getoond moet worden.
 * ─────────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_form_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Formulier globaal aan/uit
            $table->boolean('is_active')->default(true);

            // Welke persoonsgroepen worden gevraagd in het formulier
            $table->boolean('show_participant_children')->default(true);
            $table->boolean('show_participant_adults')->default(true);
            $table->boolean('show_participant_supervisors')->default(false);

            // Buiten openingsuren = privé: waarschuwing + minimumtarieven tonen
            // Als false: geen waarschuwing, geen minimumtarieven (bv. altijd-privé venue
            // die dit zelf anders communiceert, of venue die het niet relevant vindt)
            $table->boolean('outside_hours_warning_enabled')->default(true);

            $table->timestamps();

            $table->unique('tenant_id'); // één config per tenant
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_form_configs');
    }
};

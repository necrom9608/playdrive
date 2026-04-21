<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking-form v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Configuratie per stay-option voor het reservatieformulier.
 * Bepaalt of een stay-option zichtbaar is in het formulier, en wat de
 * minimumomzet is bij reservaties buiten de openingsuren (= privé).
 *
 * min_revenue_outside_hours_cents:
 *   Bedrag in eurocent. null = geen minimum (of privé niet van toepassing).
 *   Wordt getoond als waarschuwing in het formulier wanneer de gekozen
 *   datum/tijd buiten de openingsuren valt.
 *   Voorbeelden Game-INN:
 *     2 uur      →  30000 (€300)
 *     halve dag  →  60000 (€600)
 *     hele dag   → 100000 (€1000)
 * ─────────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_form_stay_option_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('stay_option_id')->constrained()->onDelete('cascade');

            // Zichtbaar in het formulier
            $table->boolean('show_in_form')->default(true);

            // Minimumomzet bij buiten-uren reservatie, in eurocent
            // null = geen minimum van toepassing voor deze stay-option
            $table->unsignedInteger('min_revenue_outside_hours_cents')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'stay_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_form_stay_option_configs');
    }
};

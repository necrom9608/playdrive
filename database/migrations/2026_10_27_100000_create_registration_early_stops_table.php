<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vroegtijdig stoppen: deelnemers van een groep die vroeger stoppen met
 * spelen. Ze blijven op de groepsrekening, maar betalen speeltijd tot
 * stopped_at. Optioneel gekoppeld aan een abonnee van de groep.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_early_stops')) {
            return;
        }

        Schema::create('registration_early_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->unsignedSmallInteger('participants_children')->default(0);
            $table->unsignedSmallInteger('participants_adults')->default(0);
            $table->foreignId('registration_subscriber_id')->nullable()->constrained('registration_subscribers')->nullOnDelete();
            $table->dateTime('stopped_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_early_stops');
    }
};

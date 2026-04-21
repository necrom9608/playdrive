<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opening-hours v1
 * Aanmaken van de opening_hours tabel.
 * Bevat de openingsuren per tenant, per season_key en per weekdag.
 * Weekdag: 1=maandag, 7=zondag (ISO 8601).
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('season_key', 50);
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_open')->default(true);
            $table->time('open_from')->nullable();
            $table->time('open_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'season_key', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};

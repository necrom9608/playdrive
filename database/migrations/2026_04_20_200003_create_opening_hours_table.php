<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opening-hours v1
 * Aanmaken van de opening_hours tabel.
 * Bevat de openingsuren per tenant, per season_key en per weekdag.
 * Weekdag: 1=maandag, 7=zondag (ISO 8601).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('season_key', 50);           // bijv. regular, school_vac, summer
            $table->unsignedTinyInteger('weekday');      // 1=ma ... 7=zo
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

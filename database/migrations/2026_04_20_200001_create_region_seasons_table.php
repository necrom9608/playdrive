<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opening-hours v1
 * Aanmaken van de region_seasons tabel.
 * Bevat de vakantieperiodes per regio, centraal beheerd door het platform.
 * Tenants erven deze periodes via hun region_code.
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 10)->index();
            $table->string('season_key', 50);
            $table->string('season_name', 100);
            $table->date('date_from');
            $table->date('date_until');
            $table->unsignedTinyInteger('priority')->default(20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_seasons');
    }
};

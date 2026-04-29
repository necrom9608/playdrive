<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_photos v1
 * Foto's voor de publieke venuepagina.
 * sort_order bepaalt de volgorde in de galerij.
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('path');
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_photos');
    }
};

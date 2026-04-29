<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_activities v1
 * Activiteiten/aanbod dat een venue toont op de publieke pagina.
 *
 * icon_key: verwijzing naar een icoon-bibliotheek (bv. lucide of heroicons).
 * Welke set we gebruiken bepalen we later — kolom is nu nullable.
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_activities');
    }
};

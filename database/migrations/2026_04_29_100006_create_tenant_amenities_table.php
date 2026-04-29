<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_amenities v1
 * Voorzieningen op de publieke venuepagina.
 *
 * key: vooraf gedefinieerd in config/venue_amenities.php (parking, wheelchair_accessible, ...)
 * value: optionele extra tekst (bv. 'Gratis op parking achteraan')
 * is_available: true = de voorziening is er, false = expliciet niet
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_amenities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('key', 50);
            $table->string('value', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_amenities');
    }
};

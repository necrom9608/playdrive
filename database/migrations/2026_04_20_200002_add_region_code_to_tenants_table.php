<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opening-hours v1
 * Voegt region_code toe aan de tenants tabel.
 * Via deze koppeling weet het systeem welke schoolvakanties van toepassing zijn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('region_code', 10)->nullable()->after('country');

            $table->foreign('region_code')
                ->references('code')
                ->on('regions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['region_code']);
            $table->dropColumn('region_code');
        });
    }
};

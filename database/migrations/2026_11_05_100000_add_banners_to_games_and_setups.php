<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banner per game en per speelplek, voor de tegels in Frontdesk → Zaal (zie Domain\Games\GameArtwork).
 *
 *   banner_path        bestand op de disk "local" (banners/{tenant}/...)
 *   banner_source      steam | steamgriddb | arcadedb | upload | removed
 *   banner_ratio       breedte / hoogte (marquees zijn breder dan Steam-banners)
 *   banner_fetched_at  laatste automatische poging, zodat we niet blijven zoeken
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['games', 'setups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'banner_path')) {
                    $table->string('banner_path')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'banner_source')) {
                    $table->string('banner_source', 20)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'banner_ratio')) {
                    $table->decimal('banner_ratio', 5, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'banner_fetched_at')) {
                    $table->timestamp('banner_fetched_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['games', 'setups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['banner_path', 'banner_source', 'banner_ratio', 'banner_fetched_at']);
            });
        }
    }
};

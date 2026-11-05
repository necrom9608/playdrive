<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automatische banners (zie Domain\Games\GameArtwork):
 *   setups.mame_name      MAME-romnaam van een arcadekast (zelf gevonden of ingegeven) → echte marquee
 *   *.banner_manual       zelf gekozen of geüpload: automatisch zoeken laat deze banner staan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setups', function (Blueprint $table) {
            if (! Schema::hasColumn('setups', 'mame_name')) {
                $table->string('mame_name', 32)->nullable();
            }
        });

        foreach (['games', 'setups'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'banner_manual')) {
                    $table->boolean('banner_manual')->default(false);
                }
            });

            // Wat al via de backoffice gekozen werd (alles behalve automatisch Steam) als manueel markeren.
            DB::table($tableName)
                ->whereNotNull('banner_path')
                ->where('banner_source', '!=', 'steam')
                ->update(['banner_manual' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('setups', function (Blueprint $table) {
            $table->dropColumn(['mame_name', 'banner_manual']);
        });
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('banner_manual');
        });
    }
};

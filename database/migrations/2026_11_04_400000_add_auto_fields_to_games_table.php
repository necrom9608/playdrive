<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * games.auto_fields: welke velden automatisch ingevuld werden en door welke bron
 * ({"local_players_max": "igdb|pcgw|steam", "lan_multiplayer": …, "categories": "tags|steam"}).
 * Zo kan "Opnieuw ophalen" die verbeteren zonder aan zelf aangeduide waarden te komen.
 *
 * Bestaande games die al opgehaald werden maar nog niet bekeken zijn (= niemand heeft ze
 * aangepast), worden als automatisch gemarkeerd.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('games', 'auto_fields')) {
            Schema::table('games', function (Blueprint $table) {
                $table->json('auto_fields')->nullable()->after('metadata_fetched_at');
            });
        }

        DB::table('games')
            ->whereNotNull('metadata_fetched_at')
            ->whereNull('reviewed_at')
            ->whereNull('auto_fields')
            ->orderBy('id')
            ->get(['id', 'local_players_max', 'lan_multiplayer'])
            ->each(function ($game) {
                $auto = [];
                if ($game->local_players_max !== null) {
                    $auto['local_players_max'] = 'steam';
                }
                if ($game->lan_multiplayer !== null) {
                    $auto['lan_multiplayer'] = 'steam';
                }
                if (DB::table('game_game_category')->where('game_id', $game->id)->exists()) {
                    $auto['categories'] = 'tags';
                }
                if ($auto) {
                    DB::table('games')->where('id', $game->id)->update(['auto_fields' => json_encode($auto)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('auto_fields');
        });
    }
};

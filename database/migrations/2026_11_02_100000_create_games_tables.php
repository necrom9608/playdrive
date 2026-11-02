<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamecatalogus per venue, gevoed door Playscope.
 *
 * - games:              elke game die een Playscope-PC ooit meldde (per venue, per bron).
 *                       reviewed_at = NULL → "nieuw, nog in te delen" in de backoffice.
 * - game_categories:    eigen indeling van de venue (Party, Kids, Racing, ...).
 * - game_game_category: welke game in welke categorie zit.
 * - venue_device_games: welke game nu op welk toestel geïnstalleerd is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('games')) {
            Schema::create('games', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('source', 20)->default('steam');      // steam | teknoparrot | ...
                $table->string('external_id', 64);                    // Steam app-ID, TeknoParrot-profiel, ...
                $table->string('name');
                $table->unsignedTinyInteger('local_players_max')->nullable(); // spelers op één scherm
                $table->boolean('lan_multiplayer')->nullable();               // samen over LAN/online
                $table->boolean('is_hidden')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'source', 'external_id']);
            });
        }

        if (! Schema::hasTable('game_categories')) {
            Schema::create('game_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 60);
                $table->string('color', 9)->nullable();               // #rrggbb
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('game_game_category')) {
            Schema::create('game_game_category', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
                $table->foreignId('game_category_id')->constrained('game_categories')->cascadeOnDelete();
                $table->primary(['game_id', 'game_category_id']);
            });
        }

        if (! Schema::hasTable('venue_device_games')) {
            Schema::create('venue_device_games', function (Blueprint $table) {
                $table->foreignId('venue_device_id')->constrained('venue_devices')->cascadeOnDelete();
                $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
                $table->timestamp('installed_at')->nullable();        // eerste keer gemeld op dit toestel
                $table->primary(['venue_device_id', 'game_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_device_games');
        Schema::dropIfExists('game_game_category');
        Schema::dropIfExists('game_categories');
        Schema::dropIfExists('games');
    }
};

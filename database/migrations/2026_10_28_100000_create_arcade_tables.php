<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arcade-scores (Asphyxia → Playdrive).
 *
 * - arcade_sources: een rhythm-server van een venue (pusht met een eigen token)
 * - arcade_players: een Asphyxia-profiel (refid) bij een venue, optioneel gekoppeld aan een account
 * - arcade_cards:   kaarten (E004-/FeliCa-id) van een speler
 * - arcade_songs:   songtitels per game (gedeeld over venues)
 * - arcade_scores:  beste resultaat per speler/song/chart
 * - arcade_plays:   speelhistoriek (echte plays of afgeleide events, zie playdrive-sync)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arcade_sources')) {
            Schema::create('arcade_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->char('token_hash', 64)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_host')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('arcade_players')) {
            Schema::create('arcade_players', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('refid', 32);
                $table->string('name')->nullable();
                $table->json('games')->nullable();          // {drs: {name, id}, ddr: {...}}
                $table->char('pin_hash', 64)->nullable();   // sha256("refid:pin")
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->string('linked_via', 16)->nullable(); // card | manual | staff
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('last_played_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'refid']);
                $table->index('account_id');
            });
        }

        if (! Schema::hasTable('arcade_cards')) {
            Schema::create('arcade_cards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('arcade_player_id')->constrained('arcade_players')->cascadeOnDelete();
                $table->string('cid', 16);
                $table->string('print', 16)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'cid']);
                $table->index(['tenant_id', 'print']);
            });
        }

        if (! Schema::hasTable('arcade_songs')) {
            Schema::create('arcade_songs', function (Blueprint $table) {
                $table->id();
                $table->string('game', 16);
                $table->unsignedInteger('song_id');
                $table->string('title')->nullable();
                $table->string('artist')->nullable();
                $table->json('levels')->nullable();          // {"NORMAL": 5, ...}
                $table->timestamps();

                $table->unique(['game', 'song_id']);
            });
        }

        if (! Schema::hasTable('arcade_scores')) {
            Schema::create('arcade_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('arcade_player_id')->constrained('arcade_players')->cascadeOnDelete();
                $table->string('game', 16);
                $table->unsignedInteger('song_id');
                $table->string('chart', 24);
                $table->unsignedTinyInteger('chart_order')->default(0);
                $table->unsignedInteger('score')->nullable();
                $table->integer('grade')->nullable();
                $table->string('grade_label', 16)->nullable();
                $table->integer('clear')->nullable();
                $table->string('clear_label', 16)->nullable();
                $table->unsignedInteger('combo')->nullable();
                $table->unsignedInteger('play_count')->nullable();
                $table->json('extra')->nullable();
                $table->timestamp('achieved_at')->nullable();   // wanneer de beste score gehaald werd
                $table->timestamp('last_played_at')->nullable();
                $table->timestamps();

                $table->unique(['arcade_player_id', 'game', 'song_id', 'chart'], 'arcade_scores_chart_unique');
                $table->index(['tenant_id', 'game', 'song_id', 'chart', 'score'], 'arcade_scores_ranking');
            });
        }

        if (! Schema::hasTable('arcade_plays')) {
            Schema::create('arcade_plays', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('arcade_player_id')->constrained('arcade_players')->cascadeOnDelete();
                $table->string('source_key', 160);
                $table->string('game', 16);
                $table->unsignedInteger('song_id');
                $table->string('chart', 24);
                $table->unsignedTinyInteger('chart_order')->default(0);
                $table->unsignedInteger('score')->nullable();     // null = enkel "gespeeld" (best-only games)
                $table->integer('grade')->nullable();
                $table->string('grade_label', 16)->nullable();
                $table->integer('clear')->nullable();
                $table->string('clear_label', 16)->nullable();
                $table->unsignedInteger('combo')->nullable();
                $table->unsignedSmallInteger('plays')->default(1);
                $table->boolean('is_new_best')->default(false);
                $table->json('extra')->nullable();
                $table->timestamp('played_at');
                $table->timestamps();

                $table->unique(['tenant_id', 'source_key']);
                $table->index(['arcade_player_id', 'played_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arcade_plays');
        Schema::dropIfExists('arcade_scores');
        Schema::dropIfExists('arcade_songs');
        Schema::dropIfExists('arcade_cards');
        Schema::dropIfExists('arcade_players');
        Schema::dropIfExists('arcade_sources');
    }
};

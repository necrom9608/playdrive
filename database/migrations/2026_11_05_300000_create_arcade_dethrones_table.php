<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Je #1 is overgenomen": wanneer iemand een eerste plaats op een chart afpakt van een
 * gekoppelde speler, bewaren we dat hier (zie ArcadeIngestService).
 *
 *   notified_at   mail verstuurd (of bewust overgeslagen) door arcade:notify-dethrones
 *   seen_at       gezien in de app (Stats-kaart op de startpagina verdwijnt dan)
 *   reclaimed_at  de speler pakte zijn #1 terug → geen mail meer, niet meer tonen
 *
 * accounts.arcade_notify_mail: mail bij een overgenomen #1 (uit te zetten in de app of via de mail).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arcade_dethrones')) {
            Schema::create('arcade_dethrones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('game', 16);
                $table->unsignedInteger('song_id');
                $table->string('chart', 24);
                $table->unsignedBigInteger('lost_account_id');
                $table->unsignedBigInteger('lost_player_id');
                $table->unsignedInteger('lost_score')->nullable();
                $table->unsignedBigInteger('by_player_id');
                $table->unsignedInteger('by_score')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('seen_at')->nullable();
                $table->timestamp('reclaimed_at')->nullable();
                $table->timestamps();

                $table->index(['lost_account_id', 'tenant_id', 'reclaimed_at'], 'arcade_dethrones_account');
                $table->index(['notified_at', 'created_at'], 'arcade_dethrones_pending');
            });
        }

        if (! Schema::hasColumn('accounts', 'arcade_notify_mail')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->boolean('arcade_notify_mail')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arcade_dethrones');

        if (Schema::hasColumn('accounts', 'arcade_notify_mail')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('arcade_notify_mail');
            });
        }
    }
};

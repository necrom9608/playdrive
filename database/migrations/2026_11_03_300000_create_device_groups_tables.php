<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groepen van Playscope-toestellen met automatische rotatie (bv. de 6 racers, 2 vliegtuigen).
 *
 * - device_groups:      groep + rotatie-instellingen + wat er nu draait (current_game_id, epoch).
 *                       epoch telt elke wissel op; zo weet een toestel dat het moet wisselen,
 *                       ook als dezelfde game opnieuw aan de beurt is.
 * - device_group_games: afspeellijst (volgorde = sort_order).
 * - venue_devices.device_group_id:      in welke groep het toestel zit (max. één).
 * - venue_devices.rotation_report(_at): laatste melding van het toestel (idle_s, fase, epoch),
 *                       om te weten of iemand nog speelt voor er gewisseld wordt.
 *
 * Zie App\Domain\Setups\DeviceRotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_groups')) {
            Schema::create('device_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 100);
                $table->unsignedSmallInteger('rotation_minutes')->default(30);  // zo lang per game
                $table->unsignedSmallInteger('quiet_seconds')->default(20);     // zo lang niemand input → wisselen
                $table->unsignedSmallInteger('grace_minutes')->default(5);      // ten laatste zoveel na de wisseltijd
                $table->boolean('is_paused')->default(false);                   // huidige game blijft staan
                $table->foreignId('current_game_id')->nullable()->constrained('games')->nullOnDelete();
                $table->unsignedInteger('epoch')->default(0);
                $table->timestamp('current_started_at')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('device_group_games')) {
            Schema::create('device_group_games', function (Blueprint $table) {
                $table->foreignId('device_group_id')->constrained('device_groups')->cascadeOnDelete();
                $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->primary(['device_group_id', 'game_id']);
            });
        }

        if (! Schema::hasColumn('venue_devices', 'device_group_id')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->foreignId('device_group_id')->nullable()->after('setup_id')->constrained('device_groups')->nullOnDelete();
                $table->json('rotation_report')->nullable()->after('idle_minutes');
                $table->timestamp('rotation_reported_at')->nullable()->after('rotation_report');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venue_devices', 'device_group_id')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('device_group_id');
                $table->dropColumn(['rotation_report', 'rotation_reported_at']);
            });
        }
        Schema::dropIfExists('device_group_games');
        Schema::dropIfExists('device_groups');
    }
};

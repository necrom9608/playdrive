<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speelbeurten van Playscope: één rij per gestarte game (statistieken: hoe vaak en hoe lang
 * per game, per toestel en in totaal). Het toestel stuurt ze door en geeft elke beurt een
 * uuid, zodat dubbel versturen geen dubbele rij geeft. Zie App\Domain\Setups\DeviceGameSessions.
 *
 * Plus venue_devices.idle_minutes: game afsluiten na zoveel minuten zonder input
 * (null = standaard 10, 0 = uit).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_device_game_sessions')) {
            Schema::create('venue_device_game_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id');
                // Toestel verwijderd: beurten blijven (met de naam van toen) voor de statistieken.
                $table->foreignId('venue_device_id')->nullable()->constrained('venue_devices')->nullOnDelete();
                $table->string('device_name')->nullable();
                $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
                $table->string('source', 20)->default('steam');
                $table->string('external_id', 64);
                $table->string('game_name')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('running_at')->nullable();   // null = game nooit echt gestart
                $table->dateTime('ended_at');
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->unsignedInteger('active_seconds')->default(0);
                $table->string('end_reason', 20);
                $table->timestamps();

                $table->index(['tenant_id', 'started_at']);
                $table->index(['tenant_id', 'game_id']);
                $table->index(['venue_device_id', 'started_at']);
            });
        }

        if (! Schema::hasColumn('venue_devices', 'idle_minutes')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->unsignedSmallInteger('idle_minutes')->nullable()->after('state_updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_device_game_sessions');
        if (Schema::hasColumn('venue_devices', 'idle_minutes')) {
            Schema::table('venue_devices', function (Blueprint $table) {
                $table->dropColumn('idle_minutes');
            });
        }
    }
};

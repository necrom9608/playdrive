<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speelplekken en toestellen.
 *
 * - setups:        fysieke speelplek in een venue (arcadekast, LAN-PC, couch-setup, ...)
 *                  met een vaste QR-code (/s/{qr_code}). Basis voor highscores,
 *                  wedstrijden (op welke setup spelen) en Playscope-statistieken.
 * - venue_devices: onbemande toestellen zonder ingelogde gebruiker (kioskscherm,
 *                  Playscope-PC). Koppelen via een code die het toestel zelf toont;
 *                  daarna authenticeert het met een eigen Bearer-token.
 *                  tenant_id is NULL zolang het toestel nog niet geclaimd is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('setups')) {
            Schema::create('setups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('type', 20)->default('arcade');     // zie App\Domain\Setups\SetupTypes
                $table->string('zone', 100)->nullable();            // LAN-room, arcadehal, ...
                $table->text('description')->nullable();
                $table->json('games')->nullable();                  // ["Daytona USA", ...]
                $table->unsignedTinyInteger('max_players')->nullable();
                $table->string('status', 20)->default('active');    // active | maintenance | archived
                $table->string('qr_code', 12)->unique();            // globaal uniek, zit in de QR-url
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('venue_devices')) {
            Schema::create('venue_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('setup_id')->nullable()->constrained('setups')->nullOnDelete();
                $table->string('type', 20);                          // kiosk | playscope
                $table->string('name')->nullable();

                // Koppelen
                $table->string('pairing_code', 8)->nullable()->unique();
                $table->char('poll_secret_hash', 64)->nullable()->unique();
                $table->timestamp('pairing_expires_at')->nullable();
                $table->timestamp('claimed_at')->nullable();

                // Authenticatie na koppelen
                $table->char('token_hash', 64)->nullable()->unique();
                $table->timestamp('token_issued_at')->nullable();
                $table->boolean('is_active')->default(true);

                // Telemetrie
                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->string('hostname', 100)->nullable();
                $table->string('platform', 40)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->json('state')->nullable();                   // {status: idle|playing|attract, game, since}
                $table->timestamp('state_updated_at')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_devices');
        Schema::dropIfExists('setups');
    }
};

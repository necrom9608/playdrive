<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slimme stekkers (IKEA DIRIGERA) per venue.
 *
 * - power_hubs:  DIRIGERA-hubs (naam, IP, token). Koppelen gebeurt aan de kassa (Tauri), want de hub
 *                is enkel in het lokale netwerk bereikbaar; het token wordt versleuteld bewaard.
 * - power_waves: opstartgolven (volgorde, tijd tussen stekkers, wachttijd na de golf).
 * - power_plugs: stekkers zoals de kassa ze van de hub uitleest, met hun speelplek en golf.
 *
 * Backoffice: Api\Backoffice\PowerController · kassa: Api\Frontdesk\FloorPowerController.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('power_hubs')) {
            Schema::create('power_hubs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 100);
                $table->string('host', 255);                      // IP of hostnaam in het lokale netwerk
                $table->text('token')->nullable();                // versleuteld (cast "encrypted")
                $table->timestamp('paired_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_error', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('power_waves')) {
            Schema::create('power_waves', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 100);
                $table->unsignedInteger('position')->default(0);
                $table->unsignedSmallInteger('stagger_seconds')->default(2);       // tussen twee stekkers
                $table->unsignedSmallInteger('delay_after_seconds')->default(20);  // na de golf
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('power_plugs')) {
            Schema::create('power_plugs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('power_hub_id')->constrained('power_hubs')->cascadeOnDelete();
                $table->string('external_id', 100);               // id van het toestel op de hub
                $table->string('name', 255);
                $table->string('room', 100)->nullable();
                $table->string('model', 100)->nullable();
                $table->foreignId('setup_id')->nullable()->constrained('setups')->nullOnDelete();
                $table->foreignId('power_wave_id')->nullable()->constrained('power_waves')->nullOnDelete();
                $table->unsignedInteger('wave_order')->default(0);
                $table->boolean('is_on')->nullable();
                $table->boolean('is_reachable')->nullable();
                $table->decimal('power_w', 8, 1)->nullable();
                $table->boolean('has_metering')->default(false);
                $table->timestamp('missing_since')->nullable();   // niet meer op de hub gevonden
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['power_hub_id', 'external_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('power_plugs');
        Schema::dropIfExists('power_waves');
        Schema::dropIfExists('power_hubs');
    }
};

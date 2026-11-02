<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opdrachten vanuit de backoffice naar een toestel (Playscope): game stoppen, herstarten,
 * pc herstarten/uitzetten, update nu, bericht tonen, kioskmodus. Het toestel haalt ze op
 * (elke 5 s) en meldt het resultaat. Zie App\Domain\Setups\DeviceCommands.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_device_commands')) {
            return;
        }
        Schema::create('venue_device_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('venue_device_id')->constrained('venue_devices')->cascadeOnDelete();
            $table->string('command', 30);
            $table->json('payload')->nullable();
            $table->string('status', 12)->default('pending');    // pending | sent | done | failed | expired
            $table->string('result', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['venue_device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_device_commands');
    }
};

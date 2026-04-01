<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('display_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->uuid('device_uuid');
            $table->string('device_token', 100)->nullable();
            $table->uuid('pairing_uuid')->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('current_mode')->default('standby');
            $table->json('current_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'device_uuid']);
            $table->index(['tenant_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_devices');
    }
};

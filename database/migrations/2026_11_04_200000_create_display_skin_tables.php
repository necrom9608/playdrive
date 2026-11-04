<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klantenscherm (tweede scherm aan de kassa): skin + filmpjes voor de standby.
 *
 * - display_settings: één rij per tenant (skin, titel, lopende banden, openingsuren tonen, CRT-effect).
 * - display_videos:   verticale filmpjes die op de standby na elkaar spelen. De server verkleint ze
 *                     met ffmpeg naar 720×1280 zonder geluid (zie Jobs\TranscodeDisplayVideo).
 *
 * Backoffice: Api\Backoffice\DisplaySkinController · scherm: Api\Display\DisplaySkinController.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('display_settings')) {
            Schema::create('display_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique();
                $table->string('skin', 30)->default('standaard');
                $table->string('title_line_1', 60)->nullable();
                $table->string('title_line_2', 60)->nullable();
                $table->string('info_text', 120)->nullable();
                $table->json('standby_ticker')->nullable();
                $table->json('order_ticker')->nullable();
                $table->boolean('show_hours')->default(true);
                $table->string('crt', 10)->default('zacht');       // uit | zacht | sterk
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('display_videos')) {
            Schema::create('display_videos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title', 60)->nullable();
                $table->string('zone', 20)->nullable();             // icoon in de orbit (arcade, vr, racing, …)
                $table->string('status', 20)->default('uploading'); // uploading | processing | ready | failed
                $table->string('original_name', 255)->nullable();
                $table->string('upload_path', 255)->nullable();     // tijdens upload / vóór verwerking
                $table->string('path', 255)->nullable();            // afspeelbaar bestand (disk local)
                $table->unsignedBigInteger('upload_size')->default(0);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->decimal('duration_seconds', 8, 2)->nullable();
                $table->string('note', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('display_videos');
        Schema::dropIfExists('display_settings');
    }
};

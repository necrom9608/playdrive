<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenant_links v1
 * Externe links voor de publieke venuepagina (social media, etc.).
 * type: 'facebook', 'instagram', 'tiktok', 'youtube', 'twitter', 'other'
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('type', 30);
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_links');
    }
};

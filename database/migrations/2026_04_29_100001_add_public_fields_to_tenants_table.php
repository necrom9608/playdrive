<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tenants v1.public-fields
 * Voegt velden toe voor de publieke venuepagina en subscription tier.
 *
 * - tagline + public_description: tekst voor de pagina
 * - hero_image_path, video_url: media bovenaan
 * - website_url: externe site (vooral relevant voor free-tier)
 * - public_status (draft/live), public_slug (URL), published_at: publicatie
 * - subscription_tier (free/starter/pro): bepaalt later welke modules beschikbaar zijn
 * - latitude/longitude: voor kaart-weergave
 * - target_audiences: JSON array met doelgroep-tags
 *
 * Geen foreign key constraints — compatibel met MyISAM en InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('tagline', 160)->nullable()->after('company_name');
            $table->text('public_description')->nullable()->after('tagline');
            $table->string('hero_image_path')->nullable()->after('logo_path');
            $table->string('video_url')->nullable()->after('hero_image_path');
            $table->string('website_url')->nullable()->after('video_url');
            $table->string('public_status', 20)->default('draft')->after('is_active');
            $table->string('public_slug')->nullable()->unique()->after('public_status');
            $table->timestamp('published_at')->nullable()->after('public_slug');
            $table->string('subscription_tier', 20)->default('free')->after('published_at');
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('target_audiences')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn([
                'tagline',
                'public_description',
                'hero_image_path',
                'video_url',
                'website_url',
                'public_status',
                'public_slug',
                'published_at',
                'subscription_tier',
                'latitude',
                'longitude',
                'target_audiences',
            ]);
        });
    }
};

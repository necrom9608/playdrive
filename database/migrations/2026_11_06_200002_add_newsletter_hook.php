<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nieuwsbrief bij "lid worden" (/join):
 * - tenants.newsletter_url / newsletter_token: waar de inschrijving naartoe gaat
 *   (bv. game-inn.be/api/nieuwsbrief/playdrive) en het gedeelde token (versleuteld).
 *   Leeg = geen vinkje op het formulier.
 * - tenant_memberships.newsletter_opt_in_at: wanneer het lid het vinkje aanzette.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'newsletter_url')) {
                $table->string('newsletter_url', 255)->nullable();
            }
            if (! Schema::hasColumn('tenants', 'newsletter_token')) {
                $table->text('newsletter_token')->nullable();
            }
        });

        if (! Schema::hasColumn('tenant_memberships', 'newsletter_opt_in_at')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->timestamp('newsletter_opt_in_at')->nullable()->after('frontdesk_seen_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['newsletter_url', 'newsletter_token'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('tenant_memberships', 'newsletter_opt_in_at')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->dropColumn('newsletter_opt_in_at');
            });
        }
    }
};

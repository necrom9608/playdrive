<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Familieabonnement.
 *
 * - tenant_memberships.family_membership_id: gezinslid → hoofdabonnement
 *   (membership_type = 'family'). De geldigheid van gezinsleden volgt het hoofd.
 * - accounts.managed_by_account_id: beheerd account (bv. kind zonder e-mail)
 *   onder het account van het hoofd.
 * - tenants.family_max_adults / family_max_children: optionele limieten per
 *   zaak (null = onbeperkt). Het hoofd telt mee als volwassene.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenant_memberships', 'family_membership_id')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->unsignedBigInteger('family_membership_id')->nullable()->after('membership_type');
                $table->index('family_membership_id');
                $table->foreign('family_membership_id')
                    ->references('id')->on('tenant_memberships')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('accounts', 'managed_by_account_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('managed_by_account_id')->nullable()->after('id');
                $table->index('managed_by_account_id');
                $table->foreign('managed_by_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'family_max_adults')) {
                $table->unsignedSmallInteger('family_max_adults')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'family_max_children')) {
                $table->unsignedSmallInteger('family_max_children')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_memberships', 'family_membership_id')) {
            Schema::table('tenant_memberships', function (Blueprint $table) {
                $table->dropForeign(['family_membership_id']);
                $table->dropIndex(['family_membership_id']);
                $table->dropColumn('family_membership_id');
            });
        }

        if (Schema::hasColumn('accounts', 'managed_by_account_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropForeign(['managed_by_account_id']);
                $table->dropIndex(['managed_by_account_id']);
                $table->dropColumn('managed_by_account_id');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['family_max_adults', 'family_max_children']);
        });
    }
};
